<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Contest;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function authenticatedUser(array $attributes = []): array
    {
        $user = User::factory()->create($attributes + ['status' => 'active']);
        $token = Str::random(80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'last_seen_at' => now(),
            'expires_at' => now()->addDay(),
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

    private function createPost(int $userId, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $userId,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Reportable post',
            'status' => 'published',
            'moderation_status' => 'clean',
        ], $attributes));
    }

    public function test_report_meta_lists_target_types_and_reasons(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/reports/meta')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('target_types.0', 'user')
            ->assertJsonPath('reasons.0.value', 'spam')
            ->assertJsonPath('description_max_length', 1000);
    }

    public function test_a_user_can_report_a_post_and_it_reaches_the_moderation_queue(): void
    {
        [$reporter, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPost($author->id);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', [
                'target_type' => 'post',
                'target_id' => $post->id,
                'reason' => 'harassment',
                'description' => 'Targeted abuse in the caption.',
            ])
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('already_reported', false)
            ->assertJsonPath('report.status', 'open')
            ->assertJsonPath('report.reason', 'harassment');

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $reporter->id,
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'harassment',
            'status' => 'open',
        ]);

        // The admin content list needs to surface it as reported.
        $this->assertTrue((bool) $post->fresh()->is_reported);
    }

    public function test_a_user_can_report_another_user(): void
    {
        [, $token] = $this->authenticatedUser();
        $target = User::factory()->create();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', [
                'target_type' => 'user',
                'target_id' => $target->id,
                'reason' => 'fake',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('reports', ['target_type' => 'user', 'target_id' => $target->id]);
    }

    public function test_reporting_the_same_target_twice_does_not_create_a_second_open_report(): void
    {
        [, $token] = $this->authenticatedUser();
        $post = $this->createPost(User::factory()->create()->id);

        $payload = ['target_type' => 'post', 'target_id' => $post->id, 'reason' => 'spam'];

        $this->withHeaders($this->headers($token))->postJson('/api/reports', $payload)->assertCreated();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', $payload)
            ->assertOk()
            ->assertJsonPath('already_reported', true);

        $this->assertSame(1, Report::count());
    }

    public function test_a_user_cannot_report_their_own_content_or_themselves(): void
    {
        [$reporter, $token] = $this->authenticatedUser();
        $ownPost = $this->createPost($reporter->id);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', ['target_type' => 'post', 'target_id' => $ownPost->id, 'reason' => 'spam'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot report your own content.');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', ['target_type' => 'user', 'target_id' => $reporter->id, 'reason' => 'spam'])
            ->assertStatus(422);

        $this->assertSame(0, Report::count());
    }

    public function test_reporting_something_that_does_not_exist_is_a_404(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', ['target_type' => 'post', 'target_id' => 999999, 'reason' => 'spam'])
            ->assertStatus(404);
    }

    public function test_an_unsupported_target_type_or_reason_is_rejected(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', ['target_type' => 'memory', 'target_id' => 1, 'reason' => 'spam'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That kind of content cannot be reported.');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', ['target_type' => 'post', 'target_id' => 1, 'reason' => 'not_a_reason'])
            ->assertStatus(422);
    }

    public function test_only_a_conversation_member_can_report_a_message(): void
    {
        [$outsider, $outsiderToken] = $this->authenticatedUser();
        [$member, $memberToken] = $this->authenticatedUser();
        $sender = User::factory()->create();

        $conversation = Conversation::create([
            'type' => 'direct',
            'created_by_user_id' => $sender->id,
        ]);

        foreach ([$sender->id, $member->id] as $participantId) {
            ConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id' => $participantId,
                'role' => 'member',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $sender->id,
            'message_type' => 'text',
            'body' => 'Abusive message',
            'sent_at' => now(),
        ]);

        // A guessed message id from outside the conversation must look missing.
        $this->withHeaders($this->headers($outsiderToken))
            ->postJson('/api/reports', ['target_type' => 'message', 'target_id' => $message->id, 'reason' => 'harassment'])
            ->assertStatus(404);

        $this->withHeaders($this->headers($memberToken))
            ->postJson('/api/reports', ['target_type' => 'message', 'target_id' => $message->id, 'reason' => 'harassment'])
            ->assertCreated();

        $this->assertSame(1, Report::count());
    }

    public function test_comment_and_contest_targets_are_supported(): void
    {
        [, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPost($author->id);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'Rude comment',
            'status' => 'active',
        ]);

        $contest = Contest::create([
            'slug' => 'reportable-'.uniqid(),
            'title' => 'Reportable contest',
            'creator_user_id' => $author->id,
            'category' => 'community',
            'contest_type' => 'Group Contest',
            'contest_behavior_type' => 'group',
            'status' => 'active',
            'voting_type' => 'community',
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', ['target_type' => 'comment', 'target_id' => $comment->id, 'reason' => 'hate'])
            ->assertCreated();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', ['target_type' => 'contest', 'target_id' => $contest->id, 'reason' => 'fake'])
            ->assertCreated();

        $this->assertTrue((bool) $comment->fresh()->is_reported);
        $this->assertTrue((bool) $contest->fresh()->is_reported);
    }

    public function test_a_user_can_see_their_own_report_history(): void
    {
        [$reporter, $token] = $this->authenticatedUser();
        $post = $this->createPost(User::factory()->create()->id);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', ['target_type' => 'post', 'target_id' => $post->id, 'reason' => 'spam'])
            ->assertCreated();

        // Somebody else's report must not appear.
        Report::create([
            'reporter_user_id' => User::factory()->create()->id,
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'nudity',
            'status' => 'open',
        ]);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/reports/mine')
            ->assertOk()
            ->assertJsonCount(1, 'reports')
            ->assertJsonPath('reports.0.reason', 'spam')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_reporting_requires_authentication(): void
    {
        $this->postJson('/api/reports', ['target_type' => 'post', 'target_id' => 1, 'reason' => 'spam'])
            ->assertStatus(401);
    }
}
