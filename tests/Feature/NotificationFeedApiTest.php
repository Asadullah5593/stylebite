<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFeedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_notifications_are_excluded_from_list_count_and_pagination(): void
    {
        [$user, $token, $actor] = $this->userWithActor();

        // 12 visible + 5 hidden message-type, all unread and interleaved.
        foreach (range(1, 12) as $i) {
            $this->notificationFor($user, $actor, 'comment', "comment {$i}");
        }
        foreach (range(1, 5) as $i) {
            $this->notificationFor($user, $actor, 'message', "chat msg {$i}");
        }

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(10, 'notifications')      // page 1 of the visible 12
            ->assertJsonPath('unread_count', 12)        // not 17
            ->assertJsonPath('pagination.total', 12)    // not 17
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.has_more_pages', true);

        foreach ($response->json('notifications') as $item) {
            $this->assertNotSame('message', $item['type']);
        }

        // Page 2 renders the remaining 2 — "load more" never fetches a page that
        // renders nothing new.
        $this->withHeaders($this->headers($token))
            ->getJson('/api/notifications?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('pagination.has_more_pages', false);
    }

    public function test_read_all_and_clear_only_touch_visible_notifications(): void
    {
        [$user, $token, $actor] = $this->userWithActor();

        $this->notificationFor($user, $actor, 'comment', 'visible');
        $messageNotification = $this->notificationFor($user, $actor, 'message', 'hidden');

        $this->withHeaders($this->headers($token))
            ->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('updated_count', 1);   // matches what the feed shows

        $this->withHeaders($this->headers($token))
            ->deleteJson('/api/notifications/clear')
            ->assertOk()
            ->assertJsonPath('deleted_count', 1);

        // The message row survives: it is the push-delivery audit trail.
        $this->assertDatabaseHas('notifications', [
            'id' => $messageNotification->id,
            'is_read' => false,
        ]);
    }

    private function userWithActor(): array
    {
        $user = User::factory()->create(['username' => 'notif_user']);
        $actor = User::factory()->create(['username' => 'notif_actor']);

        $token = hash('sha256', 'notif-token').str_repeat('n', 16);
        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'web',
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $token, $actor];
    }

    private function notificationFor(User $user, User $actor, string $type, string $body): Notification
    {
        return Notification::query()->create([
            'recipient_user_id' => $user->id,
            'actor_user_id' => $actor->id,
            'type' => $type,
            'entity_type' => $type === 'message' ? 'message' : 'comment',
            'entity_id' => 1,
            'title' => ucfirst($type),
            'body' => $body,
            'is_read' => false,
            'delivery_status' => 'sent',
        ]);
    }

    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
