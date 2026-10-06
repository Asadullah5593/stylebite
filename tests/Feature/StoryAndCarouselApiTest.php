<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoryAndCarouselApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_carousel_post_keeps_every_photo_in_order(): void
    {
        Storage::fake('public');
        Http::fake();

        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders($this->headers($token))->post('/api/posts', [
            'post_type' => 'outfit',
            'file' => UploadedFile::fake()->image('look-1.jpg'),
            'images' => [
                UploadedFile::fake()->image('look-2.jpg'),
                UploadedFile::fake()->image('look-3.jpg'),
            ],
            'caption' => 'Three looks',
            'visibility' => 'public',
            'rating_enabled' => false,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('post.media_kind', 'carousel')
            ->assertJsonPath('post.is_story', false)
            ->assertJsonCount(3, 'post.media')
            ->assertJsonCount(3, 'post.images');

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'media_kind' => 'carousel',
            'is_story' => 0,
        ]);

        $this->assertDatabaseCount('post_media', 3);
    }

    public function test_video_post_rejects_extra_photos(): void
    {
        Storage::fake('public');
        Http::fake();

        [, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))->post('/api/posts', [
            'post_type' => 'outfit',
            'file' => UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
            'images' => [
                UploadedFile::fake()->image('still.jpg'),
            ],
            'caption' => 'Clip',
            'visibility' => 'public',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    public function test_story_post_stays_out_of_the_feed_and_shows_in_stories(): void
    {
        Storage::fake('public');
        Http::fake();

        [$author, $authorToken] = $this->authenticatedUser();
        [$follower, $followerToken] = $this->authenticatedUser();
        [, $strangerToken] = $this->authenticatedUser();

        UserFollow::query()->create([
            'follower_user_id' => $follower->id,
            'following_user_id' => $author->id,
            'status' => 'accepted',
        ]);

        $created = $this->withHeaders($this->headers($authorToken))->post('/api/posts', [
            'post_type' => 'outfit',
            'file' => UploadedFile::fake()->image('story.jpg'),
            'caption' => '#sbstory night out',
            'tags' => 'sbstory',
            'visibility' => 'public',
            'rating_enabled' => 0,
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('post.is_story', true);

        $storyId = $created->json('post.id');

        $this->assertDatabaseHas('posts', [
            'id' => $storyId,
            'is_story' => 1,
        ]);

        $feed = $this->withHeaders($this->headers($followerToken))
            ->getJson('/api/feed/home?type=outfit&discover_type=for_you')
            ->assertOk()
            ->json('feed');
        $feedIds = collect(is_array($feed) ? $feed : [])
            ->pluck('id')
            ->all();
        $this->assertNotContains($storyId, $feedIds);

        $this->withHeaders($this->headers($authorToken))
            ->getJson('/api/profiles/'.$author->username.'/overview')
            ->assertOk()
            ->assertJsonPath('outfit_posts', []);

        $this->withHeaders($this->headers($followerToken))
            ->getJson('/api/stories')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('data.0.username', $author->username)
            ->assertJsonPath('data.0.items.0.id', $storyId);

        $this->withHeaders($this->headers($strangerToken))
            ->getJson('/api/stories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $token = bin2hex(random_bytes(40));

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'web',
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $token];
    }
}
