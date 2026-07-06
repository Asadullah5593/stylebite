<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Profile;
use App\Models\UserBlock;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reels_endpoint_returns_only_video_posts_for_requested_type(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create([
            'username' => 'alex.style',
            'full_name' => 'Alex Style',
        ]);

        Profile::create([
            'user_id' => $author->id,
            'display_name' => '@alex.style',
            'city' => 'Karachi',
            'country' => 'Pakistan',
        ]);

        $reelPost = $this->createPost($author->id, [
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'video',
            'feed_type' => 'style',
            'caption' => 'Casual streetwear look for summer.',
            'location_name' => 'Karachi',
            'rating_avg' => 4.5,
            'rating_count' => 20,
        ]);

        PostMedia::create([
            'post_id' => $reelPost->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_url' => 'https://example.com/reel.mp4',
            'thumbnail_url' => 'https://example.com/reel.jpg',
            'duration_seconds' => 12,
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        $imageOnlyPost = $this->createPost($author->id, [
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Image only post',
        ]);

        PostMedia::create([
            'post_id' => $imageOnlyPost->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_url' => 'https://example.com/image.jpg',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        $foodVideoPost = $this->createPost($author->id, [
            'post_type' => 'food',
            'content_type' => 'food',
            'media_kind' => 'video',
            'feed_type' => 'bite',
            'caption' => 'Food reel',
        ]);

        PostMedia::create([
            'post_id' => $foodVideoPost->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_url' => 'https://example.com/food-reel.mp4',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/reels?type=outfit&page=1');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('type', 'outfit')
            ->assertJsonCount(1, 'reels')
            ->assertJsonPath('reels.0.id', $reelPost->id)
            ->assertJsonPath('reels.0.primary_media_type', 'video')
            ->assertJsonPath('reels.0.author.username', 'alex.style')
            ->assertJsonPath('reels.0.location.name', 'Karachi')
            ->assertJsonPath('reels.0.caption', 'Casual streetwear look for summer.');
    }

    public function test_reel_detail_and_comments_endpoints_return_nested_comments_with_pagination(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPost($author->id, [
            'post_type' => 'food',
            'content_type' => 'food',
            'media_kind' => 'video',
            'feed_type' => 'bite',
            'caption' => 'Best biryani reel',
        ]);

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_url' => 'https://example.com/biryani.mp4',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'Top comment',
            'status' => 'active',
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/reels/'.$post->id.'/comments', [
                'body' => 'Viewer comment',
            ])
            ->assertCreated();

        $replyOne = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', [
                'body' => 'Reply 1',
            ])
            ->assertCreated()
            ->json('reply.id');

        $replyTwo = $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', [
                'body' => 'Reply 2',
                'parent_reply_id' => $replyOne,
            ])
            ->assertCreated()
            ->json('reply.id');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/comments/'.$comment->id.'/replies', [
                'body' => 'Reply 3',
                'parent_reply_id' => $replyTwo,
            ])
            ->assertCreated();

        $detailResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/reels/'.$post->id.'?comments_page=1');

        $detailResponse
            ->assertOk()
            ->assertJsonPath('reel.id', $post->id)
            ->assertJsonPath('comments_pagination.per_page', 10);

        $topComment = collect($detailResponse->json('comments'))->firstWhere('body', 'Top comment');

        $this->assertNotNull($topComment);
        $this->assertSame('Reply 1', $topComment['replies'][0]['body']);
        $this->assertSame('Reply 2', $topComment['replies'][0]['replies'][0]['body']);
        $this->assertSame('Reply 3', $topComment['replies'][0]['replies'][0]['replies'][0]['body']);

        $commentsResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/reels/'.$post->id.'/comments?page=1');

        $commentsResponse
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonCount(2, 'comments');
    }

    public function test_reels_endpoint_hides_reels_from_blocked_users(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $viewerBlockedAuthor = User::factory()->create();
        $authorWhoBlockedViewer = User::factory()->create();
        $visibleAuthor = User::factory()->create();

        $blockedByViewerReel = $this->createPost($viewerBlockedAuthor->id, [
            'caption' => 'Blocked by viewer reel',
        ]);

        PostMedia::create([
            'post_id' => $blockedByViewerReel->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_url' => 'https://example.com/blocked-by-viewer.mp4',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        $blockedViewerReel = $this->createPost($authorWhoBlockedViewer->id, [
            'caption' => 'Blocked viewer reel',
        ]);

        PostMedia::create([
            'post_id' => $blockedViewerReel->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_url' => 'https://example.com/blocked-viewer.mp4',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        $visibleReel = $this->createPost($visibleAuthor->id, [
            'caption' => 'Visible reel',
        ]);

        PostMedia::create([
            'post_id' => $visibleReel->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_url' => 'https://example.com/visible.mp4',
            'sort_order' => 0,
            'processing_status' => 'ready',
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
            ->getJson('/api/reels?type=outfit&page=1');

        $reelIds = collect($response->json('reels'))->pluck('id');

        $this->assertFalse($reelIds->contains($blockedByViewerReel->id));
        $this->assertFalse($reelIds->contains($blockedViewerReel->id));
        $this->assertTrue($reelIds->contains($visibleReel->id));
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $token = str_repeat('r', 80);

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
            'media_kind' => 'video',
            'feed_type' => 'style',
            'caption' => 'Reel',
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
