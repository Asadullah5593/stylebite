<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessagingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_messaging_can_be_stopped_and_resumed_via_api(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $otherUser = User::factory()->create([
            'username' => 'chat_target',
        ]);

        $conversation = Conversation::query()->create([
            'type' => 'direct',
            'created_by_user_id' => $viewer->id,
        ]);

        ConversationMember::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $viewer->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        ConversationMember::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $otherUser->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $stopResponse = $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/stop");

        $stopResponse
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Messaging stopped successfully.')
            ->assertJsonPath('chat.conversation_id', $conversation->id)
            ->assertJsonPath('chat.is_messaging_stopped', true);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
        ]);

        $this->assertNotNull($conversation->fresh()->messaging_stopped_at);
        $this->assertSame($viewer->id, (int) $conversation->fresh()->messaging_stopped_by_user_id);

        $resumeResponse = $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/resume");

        $resumeResponse
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Messaging resumed successfully.')
            ->assertJsonPath('chat.conversation_id', $conversation->id)
            ->assertJsonPath('chat.is_messaging_stopped', false);

        $this->assertNull($conversation->fresh()->messaging_stopped_at);
        $this->assertNull($conversation->fresh()->messaging_stopped_by_user_id);
    }

    public function test_stop_endpoint_is_idempotent_when_chat_is_already_stopped(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $otherUser = User::factory()->create();

        $conversation = Conversation::query()->create([
            'type' => 'direct',
            'created_by_user_id' => $viewer->id,
            'messaging_stopped_by_user_id' => $viewer->id,
            'messaging_stopped_at' => now(),
        ]);

        ConversationMember::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $viewer->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        ConversationMember::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $otherUser->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/stop");

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Messaging is already stopped for this chat.')
            ->assertJsonPath('chat.is_messaging_stopped', true);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create([
            'username' => 'chat_viewer',
        ]);
        $token = str_repeat('c', 80);

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
