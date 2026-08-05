<?php

namespace Tests\Feature;

use App\Events\Chat\ChatListUpdated;
use App\Events\Chat\MessagesDelivered;
use App\Events\Chat\MessageSent;
use App\Events\Chat\MessagesRead;
use App\Events\Chat\MessagingStateChanged;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_message_broadcasts_to_the_thread_and_the_recipient_chat_list(): void
    {
        Event::fake([MessageSent::class, ChatListUpdated::class]);

        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'realtime hello'])
            ->assertOk();

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($conversation, $viewer) {
            return $event->conversationId === (int) $conversation->id
                && $event->message['body'] === 'realtime hello'
                && (int) $event->message['sender_user_id'] === (int) $viewer->id
                && $event->message['status'] === 'sent'
                && $event->broadcastAs() === 'message.sent'
                && $event->broadcastOn()->name === 'presence-conversation.'.$conversation->id;
        });

        // The recipient's badge must update; the sender already has the HTTP response.
        Event::assertDispatched(ChatListUpdated::class, function (ChatListUpdated $event) use ($other) {
            return $event->userId === (int) $other->id
                && $event->chat['unread_count'] === 1
                && $event->totalUnreadCount === 1
                && $event->broadcastOn()->name === 'private-user.'.$other->id;
        });
    }

    public function test_reading_and_delivery_broadcast_status_updates(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();
        $otherToken = $this->tokenFor($other);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $viewer->id,
            'message_type' => 'text',
            'body' => 'status please',
            'sent_at' => now(),
        ]);

        Event::fake([MessagesDelivered::class, MessagesRead::class]);

        $this->withHeaders($this->headers($otherToken))
            ->getJson("/api/chats/{$viewer->username}/messages")
            ->assertOk();

        Event::assertDispatched(MessagesDelivered::class, function (MessagesDelivered $event) use ($conversation, $other, $message) {
            return $event->conversationId === (int) $conversation->id
                && $event->recipientUserId === (int) $other->id
                && $event->messageIds === [(int) $message->id]
                && $event->broadcastAs() === 'messages.delivered';
        });

        $this->withHeaders($this->headers($otherToken))
            ->postJson("/api/chats/{$conversation->id}/read")
            ->assertOk();

        Event::assertDispatched(MessagesRead::class, function (MessagesRead $event) use ($conversation, $other, $message) {
            return $event->conversationId === (int) $conversation->id
                && $event->readerUserId === (int) $other->id
                && $event->lastReadMessageId === (int) $message->id
                && $event->messageIds === [(int) $message->id]
                && $event->broadcastAs() === 'messages.read';
        });
    }

    public function test_read_endpoint_does_not_broadcast_when_there_is_nothing_new_to_read(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        Event::fake([MessagesRead::class]);

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/read")
            ->assertOk();

        // Free-tier message budget: never spend an event on a no-op.
        Event::assertNotDispatched(MessagesRead::class);
    }

    public function test_stop_and_resume_broadcast_state_changes(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        Event::fake([MessagingStateChanged::class]);

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/stop")
            ->assertOk();

        Event::assertDispatched(MessagingStateChanged::class, function (MessagingStateChanged $event) use ($viewer) {
            return $event->isMessagingStopped === true
                && $event->stoppedByUserId === (int) $viewer->id
                && $event->broadcastAs() === 'messaging.state';
        });

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/resume")
            ->assertOk();

        Event::assertDispatched(MessagingStateChanged::class, fn (MessagingStateChanged $event) => $event->isMessagingStopped === false);
    }

    public function test_channel_auth_allows_members_and_rejects_outsiders_and_blocked_users(): void
    {
        $this->usePusherDriver();

        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();
        $stranger = User::factory()->create(['username' => 'nosy_stranger']);
        $strangerToken = $this->tokenFor($stranger);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'presence-conversation.'.$conversation->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);

        $this->withHeaders($this->headers($strangerToken))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'presence-conversation.'.$conversation->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();

        UserBlock::query()->create([
            'blocker_user_id' => $other->id,
            'blocked_user_id' => $viewer->id,
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'presence-conversation.'.$conversation->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_private_user_channel_cannot_be_subscribed_by_another_user(): void
    {
        $this->usePusherDriver();

        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-user.'.$viewer->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-user.'.$other->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_broadcast_auth_requires_authentication(): void
    {
        [$viewer, $token, $other, $conversation] = $this->conversationBetweenUsers();

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'presence-conversation.'.$conversation->id,
            'socket_id' => '1234.5678',
        ])->assertUnauthorized();
    }

    /**
     * Channel authorisation returns an HMAC signature that only the pusher
     * broadcaster knows how to build. Signing is done locally with the app
     * secret, so dummy credentials are enough and nothing touches the network —
     * which is why these tests opt in rather than the whole suite pointing at a
     * real broadcaster.
     */
    private function usePusherDriver(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'testing-key',
            'broadcasting.connections.pusher.secret' => 'testing-secret',
            'broadcasting.connections.pusher.app_id' => 'testing-app',
            'broadcasting.connections.pusher.options.cluster' => 'ap2',
        ]);

        // Broadcast::channel() registers on the concrete broadcaster, not on the
        // manager. The callbacks in routes/channels.php were bound to the driver
        // that was default at boot, so after switching the default they have to be
        // registered again — otherwise every channel authorises as 403.
        require base_path('routes/channels.php');
    }

    private function conversationBetweenUsers(): array
    {
        $viewer = User::factory()->create(['username' => 'cast_viewer']);
        $other = User::factory()->create(['username' => 'cast_target']);
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

    private function tokenFor(User $user): string
    {
        $token = hash('sha256', 'cast-token-'.$user->id).str_repeat('y', 16);

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
