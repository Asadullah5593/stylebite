<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ChatCommentsAndReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_request_caps_at_three_messages_until_accepted(): void
    {
        [$requester, $requesterToken] = $this->authenticatedUser('requester', 'a');
        [$recipient, $recipientToken] = $this->authenticatedUser('recipient', 'b');

        $init = $this->withHeaders($this->headers($requesterToken))
            ->postJson('/api/chats/initialize', ['username' => 'recipient'])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('chat.is_pending_request', true)
            ->assertJsonPath('chat.is_incoming_request', false)
            ->assertJsonPath('chat.is_accepted', false);

        $conversationId = $init->json('chat.conversation_id');

        foreach (['one', 'two', 'three'] as $body) {
            $this->withHeaders($this->headers($requesterToken))
                ->postJson("/api/chats/{$conversationId}/messages", ['body' => $body])
                ->assertOk()
                ->assertJsonPath('status_code', 1);
        }

        $this->withHeaders($this->headers($requesterToken))
            ->postJson("/api/chats/{$conversationId}/messages", ['body' => 'four'])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0);

        $this->withHeaders($this->headers($requesterToken))
            ->postJson("/api/chats/{$conversationId}/accept")
            ->assertForbidden();

        $this->withHeaders($this->headers($recipientToken))
            ->postJson("/api/chats/{$conversationId}/accept")
            ->assertOk()
            ->assertJsonPath('chat.is_accepted', true)
            ->assertJsonPath('chat.is_pending_request', false);

        $this->withHeaders($this->headers($requesterToken))
            ->postJson("/api/chats/{$conversationId}/messages", ['body' => 'four'])
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertSame(4, Message::query()->where('conversation_id', $conversationId)->count());
        $this->assertSame($requester->id, (int) Conversation::query()->find($conversationId)->requested_by_user_id);
    }

    public function test_incoming_reply_auto_accepts_and_read_sync_unread_work(): void
    {
        [$requester, $requesterToken] = $this->authenticatedUser('requester', 'c');
        [$recipient, $recipientToken] = $this->authenticatedUser('recipient', 'd');

        $conversationId = $this->withHeaders($this->headers($requesterToken))
            ->postJson('/api/chats/initialize', ['username' => 'recipient'])
            ->json('chat.conversation_id');

        $this->withHeaders($this->headers($requesterToken))
            ->postJson("/api/chats/{$conversationId}/messages", ['body' => 'hello']);

        $this->withHeaders($this->headers($recipientToken))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('chats.0.unread_count', 1)
            ->assertJsonPath('chats.0.is_incoming_request', true)
            ->assertJsonPath('total_unread_count', 1);

        $firstId = (int) Message::query()->where('conversation_id', $conversationId)->value('id');

        $this->withHeaders($this->headers($recipientToken))
            ->postJson("/api/chats/{$conversationId}/messages", ['body' => 'hey back'])
            ->assertOk()
            ->assertJsonPath('chat.is_accepted', true);

        $this->withHeaders($this->headers($recipientToken))
            ->postJson("/api/chats/{$conversationId}/read", ['up_to_message_id' => $firstId])
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->withHeaders($this->headers($requesterToken))
            ->getJson("/api/chats/{$conversationId}/sync?after_message_id={$firstId}")
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'hey back');

        $this->withHeaders($this->headers($requesterToken))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('chats.0.unread_count', 1)
            ->assertJsonPath('total_unread_count', 1);
    }

    public function test_rejected_chat_request_is_hidden_from_the_list(): void
    {
        [, $requesterToken] = $this->authenticatedUser('requester', 'e');
        [, $recipientToken] = $this->authenticatedUser('recipient', 'f');

        $conversationId = $this->withHeaders($this->headers($requesterToken))
            ->postJson('/api/chats/initialize', ['username' => 'recipient'])
            ->json('chat.conversation_id');

        $this->withHeaders($this->headers($recipientToken))
            ->postJson("/api/chats/{$conversationId}/requests/reject")
            ->assertOk()
            ->assertJsonPath('chat.request_status', 'rejected');

        $this->withHeaders($this->headers($requesterToken))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('chats', []);

        $this->withHeaders($this->headers($recipientToken))
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonCount(0, 'chats');
    }

    public function test_broadcasting_auth_signs_private_user_channel(): void
    {
        [$user, $token] = $this->authenticatedUser('socket_user', 'g');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-user.'.$user->id,
            ])
            ->assertStatus(503);

        Config::set('broadcasting.connections.pusher.key', 'app-key');
        Config::set('broadcasting.connections.pusher.secret', 'app-secret');

        $expected = 'app-key:'.hash_hmac('sha256', '1234.5678:private-user.'.$user->id, 'app-secret');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-user.'.$user->id,
            ])
            ->assertOk()
            ->assertJsonPath('auth', $expected);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-user.'.($user->id + 99),
            ])
            ->assertForbidden();
    }

    public function test_user_can_report_comment_and_list_own_reports(): void
    {
        [$reporter, $token] = $this->authenticatedUser('reporter', 'h');
        $author = User::factory()->create(['username' => 'comment_owner']);
        $post = $this->createPost($author->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'spam comment',
            'status' => 'active',
        ]);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/reports/meta')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('description_max_length', 1000);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', [
                'target_type' => 'comment',
                'target_id' => $comment->id,
                'reason' => 'spam',
            ])
            ->assertCreated()
            ->assertJsonPath('already_reported', false);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', [
                'target_type' => 'comment',
                'target_id' => $comment->id,
                'reason' => 'spam',
            ])
            ->assertOk()
            ->assertJsonPath('already_reported', true);

        $ownComment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $reporter->id,
            'body' => 'mine',
            'status' => 'active',
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', [
                'target_type' => 'comment',
                'target_id' => $ownComment->id,
                'reason' => 'spam',
            ])
            ->assertStatus(422);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/reports/mine')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('reports.0.target_type', 'comment');

        $this->assertTrue((bool) $comment->fresh()->is_reported);
        $this->assertSame(1, Report::query()->count());
    }

    public function test_author_can_delete_comment_and_reply(): void
    {
        [$viewer, $token] = $this->authenticatedUser('deleter', 'i');
        $author = User::factory()->create();
        Profile::create(['user_id' => $author->id, 'display_name' => 'Author']);
        $post = $this->createPost($author->id, ['comment_count' => 1]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
            'body' => 'Please delete me',
            'status' => 'active',
            'reply_count' => 1,
        ]);

        $reply = CommentReply::create([
            'comment_id' => $comment->id,
            'user_id' => $viewer->id,
            'body' => 'Nested',
            'status' => 'active',
        ]);

        $this->withHeaders($this->headers($token))
            ->deleteJson('/api/feed/replies/'.$reply->id)
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertNotNull(CommentReply::withTrashed()->find($reply->id)?->deleted_at);
        $this->assertSame(0, (int) $comment->fresh()->reply_count);

        $this->withHeaders($this->headers($token))
            ->deleteJson('/api/feed/comments/'.$comment->id)
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertNotNull(Comment::withTrashed()->find($comment->id)?->deleted_at);
        $this->assertSame(0, (int) $post->fresh()->comment_count);

        $otherComment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'Not yours',
            'status' => 'active',
        ]);

        $this->withHeaders($this->headers($token))
            ->deleteJson('/api/feed/posts/'.$post->id.'/comments/'.$otherComment->id)
            ->assertForbidden();
    }

    public function test_reel_comment_delete_requires_video_post(): void
    {
        [$viewer, $token] = $this->authenticatedUser('reel_user', 'j');
        $author = User::factory()->create();
        $post = $this->createPost($author->id, [
            'media_kind' => 'video',
            'comment_count' => 1,
        ]);
        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_url' => 'https://cdn.example.com/reel.mp4',
            'sort_order' => 0,
        ]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
            'body' => 'reel comment',
            'status' => 'active',
        ]);

        $this->withHeaders($this->headers($token))
            ->deleteJson('/api/reels/'.$post->id.'/comments/'.$comment->id)
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertNotNull(Comment::withTrashed()->find($comment->id)?->deleted_at);
    }

    private function authenticatedUser(string $username, string $tokenChar): array
    {
        $user = User::factory()->create([
            'username' => $username,
        ]);
        $token = str_repeat($tokenChar, 80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'web',
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $token];
    }

    private function createPost(int $userId, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $userId,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Feed post',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'allow_comments' => true,
            'allow_shares' => true,
            'rating_enabled' => true,
            'posted_at' => now(),
            'published_at' => now(),
        ], $attributes));
    }

    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
