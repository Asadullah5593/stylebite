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

class ChatListAndBlockingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversations_without_messages_are_hidden_until_something_is_sent(): void
    {
        [$viewer, $token, $other] = $this->users();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/chats/initialize', ['username' => $other->username])
            ->assertOk();

        // Created, but nothing said yet — it must not clutter the list.
        $this->withHeaders($this->headers($token))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonCount(0, 'chats')
            ->assertJsonPath('pagination.total', 0);

        $conversationId = Conversation::query()->value('id');

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversationId}/messages", ['body' => 'now it exists'])
            ->assertOk();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonCount(1, 'chats')
            ->assertJsonPath('chats.0.conversation_id', $conversationId);
    }

    public function test_chat_list_reports_which_side_of_a_block_it_is(): void
    {
        [$viewer, $token, $other] = $this->users();
        $conversation = $this->conversationWithMessage($viewer, $other);

        // Nobody blocked: both flags false, chat visible.
        $this->withHeaders($this->headers($token))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('chats.0.is_blocked_by_me', false)
            ->assertJsonPath('chats.0.is_blocked_by_other', false)
            ->assertJsonPath('chats.0.is_blocked', false);

        // The other party blocks the viewer.
        $block = UserBlock::query()->create([
            'blocker_user_id' => $other->id,
            'blocked_user_id' => $viewer->id,
        ]);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonCount(1, 'chats')   // still listed, just flagged
            ->assertJsonPath('chats.0.is_blocked_by_me', false)
            ->assertJsonPath('chats.0.is_blocked_by_other', true)
            ->assertJsonPath('chats.0.is_blocked', true);

        // Flip it: the viewer is the one who blocked.
        $block->delete();
        UserBlock::query()->create([
            'blocker_user_id' => $viewer->id,
            'blocked_user_id' => $other->id,
        ]);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('chats.0.is_blocked_by_me', true)
            ->assertJsonPath('chats.0.is_blocked_by_other', false)
            ->assertJsonPath('chats.0.is_blocked', true);
    }

    public function test_block_flags_are_present_on_the_403_bodies(): void
    {
        [$viewer, $token, $other] = $this->users();
        $conversation = $this->conversationWithMessage($viewer, $other);

        UserBlock::query()->create([
            'blocker_user_id' => $viewer->id,
            'blocked_user_id' => $other->id,
        ]);

        // Re-entering a chat you blocked: initialize 403s, and the error body is
        // the only thing the client has to work with.
        $this->withHeaders($this->headers($token))
            ->postJson('/api/chats/initialize', ['username' => $other->username])
            ->assertForbidden()
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('is_blocked_by_me', true)
            ->assertJsonPath('is_blocked_by_other', false);

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'hi'])
            ->assertForbidden()
            ->assertJsonPath('is_blocked_by_me', true)
            ->assertJsonPath('is_blocked_by_other', false);
    }

    public function test_403_body_distinguishes_being_blocked_by_the_other_party(): void
    {
        [$viewer, $token, $other] = $this->users();
        $conversation = $this->conversationWithMessage($viewer, $other);

        UserBlock::query()->create([
            'blocker_user_id' => $other->id,
            'blocked_user_id' => $viewer->id,
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'hi'])
            ->assertForbidden()
            ->assertJsonPath('is_blocked_by_me', false)
            ->assertJsonPath('is_blocked_by_other', true);
    }

    public function test_two_unanswered_messages_are_allowed_and_the_third_is_not(): void
    {
        [$viewer, $token, $other] = $this->users();
        $conversation = $this->emptyConversation($viewer, $other);

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'first'])
            ->assertOk();

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'second'])
            ->assertOk();

        $this->withHeaders($this->headers($token))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'third'])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'Wait for reply before sending another message.');
    }

    public function test_the_cap_lifts_once_the_other_person_replies(): void
    {
        [$viewer, $token, $other] = $this->users();
        $otherToken = $this->tokenFor($other);
        $conversation = $this->emptyConversation($viewer, $other);

        foreach (['first', 'second'] as $body) {
            $this->withHeaders($this->headers($token))
                ->postJson("/api/chats/{$conversation->id}/messages", ['body' => $body])
                ->assertOk();
        }

        $this->withHeaders($this->headers($otherToken))
            ->postJson("/api/chats/{$conversation->id}/messages", ['body' => 'reply'])
            ->assertOk();

        // Once answered, the opening-message cap no longer applies.
        foreach (['third', 'fourth', 'fifth'] as $body) {
            $this->withHeaders($this->headers($token))
                ->postJson("/api/chats/{$conversation->id}/messages", ['body' => $body])
                ->assertOk();
        }
    }

    private function users(): array
    {
        $viewer = User::factory()->create(['username' => 'block_viewer']);
        $other = User::factory()->create(['username' => 'block_target']);

        return [$viewer, $this->tokenFor($viewer), $other];
    }

    private function emptyConversation(User $viewer, User $other): Conversation
    {
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

        return $conversation;
    }

    private function conversationWithMessage(User $viewer, User $other): Conversation
    {
        $conversation = $this->emptyConversation($viewer, $other);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $other->id,
            'message_type' => 'text',
            'body' => 'existing history',
            'sent_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_id' => $message->id,
            'last_message_at' => $message->sent_at,
        ])->save();

        return $conversation;
    }

    private function tokenFor(User $user): string
    {
        $token = hash('sha256', 'block-token-'.$user->id).str_repeat('z', 16);

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
