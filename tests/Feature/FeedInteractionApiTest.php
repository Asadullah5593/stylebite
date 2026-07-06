<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Profile;
use App\Models\PushNotificationLog;
use App\Models\UserBlock;
use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeedInteractionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_feed_respects_public_private_and_followers_only_visibility(): void
    {
        [$viewer, $token] = $this->authenticatedUser();

        $followedAuthor = User::factory()->create();
        Profile::create(['user_id' => $followedAuthor->id, 'display_name' => 'Followed Author']);

        UserFollow::query()->create([
            'follower_user_id' => $viewer->id,
            'following_user_id' => $followedAuthor->id,
            'status' => 'accepted',
        ]);

        $otherAuthor = User::factory()->create();

        $visiblePublic = $this->createPost($otherAuthor->id, [
            'caption' => 'Visible public',
            'visibility' => 'public',
        ]);

        $visibleFollowers = $this->createPost($followedAuthor->id, [
            'caption' => 'Visible followers only',
            'visibility' => 'followers_only',
        ]);

        $hiddenFollowers = $this->createPost($otherAuthor->id, [
            'caption' => 'Hidden followers only',
            'visibility' => 'followers_only',
        ]);

        $ownPrivate = $this->createPost($viewer->id, [
            'caption' => 'Own private',
            'visibility' => 'private',
        ]);

        $hiddenPrivate = $this->createPost($otherAuthor->id, [
            'caption' => 'Hidden private',
            'visibility' => 'private',
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/home?type=outfit&page=1');

        $feedIds = collect($response->json('feed'))->pluck('id');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertTrue($feedIds->contains($visiblePublic->id));
        $this->assertTrue($feedIds->contains($visibleFollowers->id));
        $this->assertTrue($feedIds->contains($ownPrivate->id));
        $this->assertFalse($feedIds->contains($hiddenFollowers->id));
        $this->assertFalse($feedIds->contains($hiddenPrivate->id));
    }

    public function test_feed_detail_returns_paginated_comments_with_nested_replies(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        Profile::create(['user_id' => $author->id, 'display_name' => 'Author']);

        $post = $this->createPost($author->id);

        for ($i = 0; $i < 10; $i++) {
            Comment::create([
                'post_id' => $post->id,
                'user_id' => $author->id,
                'body' => 'Comment '.$i,
                'status' => 'active',
            ]);
        }

        $firstComment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'Comment special',
            'status' => 'active',
            'created_at' => now()->addSecond(),
            'updated_at' => now()->addSecond(),
        ]);

        $reply = CommentReply::create([
            'comment_id' => $firstComment->id,
            'user_id' => $author->id,
            'body' => 'Reply level 1',
            'status' => 'active',
        ]);

        CommentReply::create([
            'comment_id' => $firstComment->id,
            'parent_reply_id' => $reply->id,
            'user_id' => $author->id,
            'body' => 'Reply level 2',
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/posts/'.$post->id.'?comments_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('comments_pagination.per_page', 10)
            ->assertJsonPath('comments_pagination.total', 11)
            ->assertJsonCount(10, 'comments');

        $response
            ->assertJsonPath('comments.0.id', $firstComment->id)
            ->assertJsonPath('comments.0.replies.0.body', 'Reply level 1')
            ->assertJsonPath('comments.0.replies.0.replies.0.body', 'Reply level 2');
    }

    public function test_like_save_share_vote_and_comment_actions_work_for_feed_post(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPost($author->id, [
            'rating_enabled' => true,
            'allow_comments' => true,
            'allow_shares' => true,
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/like')
            ->assertOk()
            ->assertJsonPath('is_liked', true)
            ->assertJsonPath('like_count', 1);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/save')
            ->assertOk()
            ->assertJsonPath('is_saved', true)
            ->assertJsonPath('save_count', 1);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/share', [
                'share_channel' => 'copy_link',
            ])
            ->assertOk()
            ->assertJsonPath('share_count', 1);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/vote', [
                'rating_value' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('can_vote', true)
            ->assertJsonPath('viewer_rating', 4)
            ->assertJsonPath('rating_avg', 4)
            ->assertJsonPath('rating_count', 1);

        $commentResponse = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/comments', [
                'body' => 'Nice look',
            ])
            ->assertCreated()
            ->assertJsonPath('comment_count', 1);

        $commentId = $commentResponse->json('comment.id');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$commentId.'/like')
            ->assertOk()
            ->assertJsonPath('is_liked', true)
            ->assertJsonPath('like_count', 1);

        $replyResponse = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$commentId.'/replies', [
                'body' => 'Thanks',
            ])
            ->assertCreated()
            ->assertJsonPath('reply_count', 1);

        $replyId = $replyResponse->json('reply.id');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/replies/'.$replyId.'/like')
            ->assertOk()
            ->assertJsonPath('is_liked', true)
            ->assertJsonPath('like_count', 1);

        $detailResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/posts/'.$post->id);

        $detailResponse
            ->assertOk()
            ->assertJsonPath('post.engagement.like_count', 1)
            ->assertJsonPath('post.engagement.comment_count', 1)
            ->assertJsonPath('post.engagement.share_count', 1)
            ->assertJsonPath('post.engagement.save_count', 1)
            ->assertJsonPath('post.engagement.rating_avg', 4)
            ->assertJsonPath('post.viewer_state.is_liked', true)
            ->assertJsonPath('post.viewer_state.is_saved', true)
            ->assertJsonPath('post.viewer_state.viewer_rating', 4)
            ->assertJsonPath('comments.0.viewer_state.is_liked', true)
            ->assertJsonPath('comments.0.replies.0.viewer_state.is_liked', true)
            ->assertJsonPath('can_vote', true);
    }

    public function test_commenting_on_another_users_post_creates_and_sends_notification(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'firebase-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://fcm.googleapis.com/v1/projects/*' => Http::response([
                'name' => 'projects/stylebite/messages/123',
            ]),
        ]);

        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        Profile::create([
            'user_id' => $author->id,
            'display_name' => 'Post Owner',
        ]);

        DeviceToken::create([
            'user_id' => $author->id,
            'device_id' => 'device-1',
            'platform' => 'android',
            'push_token' => 'push-token-1',
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        $post = $this->createPost($author->id);

        $response = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/comments', [
                'body' => 'Nice post from another user',
            ])
            ->assertCreated();

        $commentId = $response->json('comment.id');

        $this->assertDatabaseHas('notifications', [
            'recipient_user_id' => $author->id,
            'actor_user_id' => $viewer->id,
            'type' => 'comment',
            'entity_type' => 'comment',
            'entity_id' => $commentId,
            'delivery_status' => 'sent',
        ]);

        $notification = Notification::query()->where('entity_id', $commentId)->firstOrFail();

        $this->assertNotNull($notification->push_sent_at);
        $this->assertDatabaseHas('push_notification_logs', [
            'notification_id' => $notification->id,
            'user_id' => $author->id,
            'provider' => 'fcm',
            'status' => 'sent',
        ]);

        Http::assertSentCount(2);
        $this->assertSame(1, PushNotificationLog::query()->count());
    }

    public function test_vote_endpoint_returns_false_when_post_does_not_allow_voting(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPost($author->id, [
            'rating_enabled' => false,
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/vote', [
                'rating_value' => 5,
            ])
            ->assertForbidden()
            ->assertJsonPath('can_vote', false);
    }

    public function test_reply_endpoint_supports_nested_replies(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPost($author->id);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'Base comment',
            'status' => 'active',
        ]);

        $firstReplyResponse = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', [
                'body' => 'First reply',
            ])
            ->assertCreated();

        $parentReplyId = $firstReplyResponse->json('reply.id');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', [
                'body' => 'Nested reply',
                'parent_reply_id' => $parentReplyId,
            ])
            ->assertCreated()
            ->assertJsonPath('reply.parent_reply_id', $parentReplyId);

        $commentsResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/posts/'.$post->id.'/comments?page=1');

        $commentsResponse
            ->assertOk()
            ->assertJsonCount(1, 'comments')
            ->assertJsonPath('comments.0.reply_count', 2)
            ->assertJsonPath('comments.0.replies.0.body', 'First reply')
            ->assertJsonPath('comments.0.replies.0.replies.0.body', 'Nested reply');
    }

    public function test_reply_endpoint_rejects_fourth_level_reply(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPost($author->id);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'Base comment',
            'status' => 'active',
        ]);

        $replyOne = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', ['body' => 'Level 1'])
            ->assertCreated()
            ->json('reply.id');

        $replyTwo = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', [
                'body' => 'Level 2',
                'parent_reply_id' => $replyOne,
            ])
            ->assertCreated()
            ->json('reply.id');

        $replyThree = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', [
                'body' => 'Level 3',
                'parent_reply_id' => $replyTwo,
            ])
            ->assertCreated()
            ->json('reply.id');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', [
                'body' => 'Level 4',
                'parent_reply_id' => $replyThree,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only 3 levels of replies are allowed.');
    }

    public function test_home_feed_hides_posts_from_users_blocked_by_viewer_or_blocking_viewer(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $viewerBlockedAuthor = User::factory()->create();
        $authorWhoBlockedViewer = User::factory()->create();
        $visibleAuthor = User::factory()->create();

        $blockedByViewerPost = $this->createPost($viewerBlockedAuthor->id, [
            'caption' => 'Blocked by viewer',
        ]);

        $blockedViewerPost = $this->createPost($authorWhoBlockedViewer->id, [
            'caption' => 'Blocked viewer',
        ]);

        $visiblePost = $this->createPost($visibleAuthor->id, [
            'caption' => 'Visible post',
        ]);

        UserBlock::query()->create([
            'blocker_user_id' => $viewer->id,
            'blocked_user_id' => $viewerBlockedAuthor->id,
        ]);

        UserBlock::query()->create([
            'blocker_user_id' => $authorWhoBlockedViewer->id,
            'blocked_user_id' => $viewer->id,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/home?type=outfit&page=1');

        $feedIds = collect($response->json('feed'))->pluck('id');

        $this->assertFalse($feedIds->contains($blockedByViewerPost->id));
        $this->assertFalse($feedIds->contains($blockedViewerPost->id));
        $this->assertTrue($feedIds->contains($visiblePost->id));
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $token = str_repeat('p', 80);

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
