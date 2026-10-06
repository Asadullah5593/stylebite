<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAndViewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_sync_push_token_after_login(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/devices/push-token', [
                'device_id' => 'android-device-1',
                'platform' => 'android',
                'push_token' => 'fcm-token-abc',
                'app_version' => '1.4.2',
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('device.device_id', 'android-device-1')
            ->assertJsonPath('device.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'android-device-1',
            'platform' => 'android',
            'push_token' => 'fcm-token-abc',
            'app_version' => '1.4.2',
            'is_active' => 1,
        ]);
    }

    public function test_push_token_sync_moves_an_existing_token_to_the_current_user(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $other = User::factory()->create();

        DeviceToken::create([
            'user_id' => $other->id,
            'device_id' => 'old-device',
            'platform' => 'android',
            'push_token' => 'shared-fcm-token',
            'is_active' => true,
            'last_used_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/devices/push-token', [
                'device_id' => 'android-device-2',
                'platform' => 'android',
                'push_token' => 'shared-fcm-token',
                'app_version' => '1.4.3',
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'android-device-2',
            'push_token' => 'shared-fcm-token',
        ]);
        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $other->id,
            'push_token' => 'shared-fcm-token',
        ]);
    }

    public function test_views_batch_records_first_view_and_adds_watch_time_later(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPublishedPost($author->id);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/views/batch', [
                'views' => [
                    [
                        'post_id' => $post->id,
                        'watch_seconds' => 4,
                        'view_source' => 'reel',
                    ],
                    [
                        'post_id' => 0,
                        'watch_seconds' => 9,
                        'view_source' => 'reel',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('recorded', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('skipped', 1);

        $this->assertSame(1, (int) $post->fresh()->view_count);
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'viewer_user_id' => $user->id,
            'view_source' => 'reel',
            'watch_seconds' => 4,
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/views/batch', [
                'views' => [
                    [
                        'post_id' => $post->id,
                        'watch_seconds' => 6,
                        'view_source' => 'reel',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('recorded', 0)
            ->assertJsonPath('updated', 1);

        $this->assertSame(1, (int) $post->fresh()->view_count);
        $this->assertSame(10, (int) PostView::query()->where('post_id', $post->id)->value('watch_seconds'));
    }

    public function test_device_and_views_require_auth(): void
    {
        $this->postJson('/api/devices/push-token', [
            'device_id' => 'android-device-1',
            'platform' => 'android',
            'push_token' => 'fcm-token-abc',
        ])->assertUnauthorized();

        $this->postJson('/api/views/batch', [
            'views' => [
                ['post_id' => 1, 'watch_seconds' => 3, 'view_source' => 'reel'],
            ],
        ])->assertUnauthorized();
    }

    private function createPublishedPost(int $userId): Post
    {
        return Post::create([
            'user_id' => $userId,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'video',
            'feed_type' => 'style',
            'caption' => 'Reel look',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'posted_at' => now(),
            'published_at' => now(),
        ]);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = str_repeat('d', 80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'device_id' => 'android-device-1',
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $token];
    }

    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
