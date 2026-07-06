<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_a_specific_notification(): void
    {
        [$recipient, $token] = $this->authenticatedUser();
        $actor = User::factory()->create([
            'username' => 'notification_actor',
        ]);

        $notification = Notification::query()->create([
            'recipient_user_id' => $recipient->id,
            'actor_user_id' => $actor->id,
            'type' => 'system',
            'entity_type' => 'system',
            'entity_id' => null,
            'title' => 'Test notification',
            'body' => 'Delete me',
            'action_url' => null,
            'image_url' => null,
            'is_read' => false,
            'delivery_status' => 'pending',
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->deleteJson("/api/notifications/{$notification->id}");

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Notification deleted successfully.');

        $this->assertSoftDeleted('notifications', [
            'id' => $notification->id,
            'recipient_user_id' => $recipient->id,
        ]);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create([
            'username' => 'notification_viewer',
        ]);
        $token = str_repeat('n', 80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'web',
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
