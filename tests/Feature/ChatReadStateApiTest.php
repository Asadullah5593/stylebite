<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatReadStateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_is_reported_and_cleared_by_the_read_endpoint(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        $first = $this->messageFrom($conversation, $other, 'hello');
        $second = $this->messageFrom($conversation, $other, 'are you there?');

        $listResponse = $this->withHeaders($this->headers($token))->getJson('/api/chats');

        $listResponse
            ->assertOk()
            ->assertJsonPath('chats.0.unread_count', 2)
            ->assertJsonPath('total_unread_count', 2);

        $readResponse = $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/read");

        $readResponse
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('total_unread_count', 0)
            ->assertJsonPath('last_read_message_id', $second->id);

        $this->assertDatabaseHas('message_reads', [
            'message_id' => $first->id,
            'user_id' => $viewer->id,
        ]);
        $this->assertDatabaseHas('message_reads', [
            'message_id' => $second->id,
            'user_id' => $viewer->id,
        ]);

        $this->assertSame(
            $second->id,
            (int) ConversationMember::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $viewer->id)
                ->value('last_read_message_id')
        );
    }

    public function test_read_endpoint_can_stop_at_a_given_message_and_is_idempotent(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        $first = $this->messageFrom($conversation, $other, 'one');
        $this->messageFrom($conversation, $other, 'two');

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/read", ['up_to_message_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('last_read_message_id', $first->id);

        // Repeating the same call must not duplicate message_reads rows.
        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/read", ['up_to_message_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->assertSame(1, Message::find($first->id)->reads()->count());
    }

    public function test_sender_sees_status_progress_from_sent_to_delivered_to_seen(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();
        $otherToken = $this->tokenFor($other);

        $sendResponse = $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'first contact']);

        $sendResponse->assertOk()->assertJsonPath('message_data.status', 'sent');
        $messageId = $sendResponse->json('message_data.id');

        // The recipient loading the thread marks it delivered.
        $this->withHeaders($this->headers($otherToken))
            ->getJson("/api/chats/{$viewer->username}/messages")
            ->assertOk();

        $this->assertNotNull(Message::find($messageId)->delivered_at);

        $this->withHeaders($this->headers($token))
            ->getJson("/api/chats/{$other->username}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.status', 'delivered');

        $this->withHeaders($this->headers($otherToken))
            ->postJson("/api/chats/{$conversation->id}/read")
            ->assertOk();

        $this->withHeaders($this->headers($token))
            ->getJson("/api/chats/{$other->username}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.status', 'seen');
    }

    public function test_sync_returns_only_messages_after_the_cursor(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        $first = $this->messageFrom($conversation, $other, 'old');
        $second = $this->messageFrom($conversation, $other, 'new');

        $response = $this->withHeaders($this->headers($token))
            ->getJson("/api/chats/{$conversation->id}/sync?after_message_id={$first->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $second->id)
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('cursor', $second->id);
    }

    public function test_blocked_users_cannot_send_messages_or_start_chats(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        UserBlock::query()->create([
            'blocker_user_id' => $other->id,
            'blocked_user_id' => $viewer->id,
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'hello?'])
            ->assertForbidden()
            ->assertJsonPath('status_code', 0);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/chats/initialize', ['username' => $other->username])
            ->assertForbidden()
            ->assertJsonPath('status_code', 0);
    }

    public function test_presence_expires_once_the_client_stops_reporting(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        // The list only shows conversations that have a message in them.
        $this->messageFrom($conversation, $other, 'hello');

        $other->forceFill(['is_online' => true, 'last_seen_at' => now()])->save();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('chats.0.user.is_online', true);

        // Stale flag: the app was force-quit and never reported going offline.
        $other->forceFill(['is_online' => true, 'last_seen_at' => now()->subMinutes(10)])->save();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('chats.0.user.is_online', false);
    }

    private function conversationBetweenUsers(): array
    {
        $viewer = User::factory()->create(['username' => 'read_viewer']);
        $other = User::factory()->create(['username' => 'read_target']);
        $token = $this->tokenFor($viewer);

        $conversation = Conversation::query()->create([
            'type' => 'direct',
            'created_by_user_id' => $viewer->id,
        ]);

        foreach ([[$viewer, 'owner'], [$other, 'member']] as [$user, $role]) {
            ConversationMember::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        return [$viewer, $token, $other, $conversation];
    }

    private function messageFrom(Conversation $conversation, User $sender, string $body): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $sender->id,
            'message_type' => 'text',
            'body' => $body,
            'sent_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_id' => $message->id,
            'last_message_at' => $message->sent_at,
        ])->save();

        return $message;
    }

    private function tokenFor(User $user): string
    {
        $token = hash('sha256', 'token-'.$user->id).str_repeat('x', 16);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'web',
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return $token;
    }

    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
