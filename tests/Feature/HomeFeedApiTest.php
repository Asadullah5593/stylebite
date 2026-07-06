<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMedia;
use App\Models\PostRating;
use App\Models\PostTag;
use App\Models\Profile;
use App\Models\SavedPost;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeFeedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_feed_returns_paginated_posts_with_full_card_details(): void
    {
        [$viewer, $token] = $this->authenticatedUser();

        $author = User::factory()->create([
            'username' => 'kai_vibe',
            'full_name' => 'Kai Vibe',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);

        Profile::create([
            'user_id' => $author->id,
            'display_name' => '@kai_vibe',
            'city' => 'Tokyo',
            'country' => 'JP',
            'vibe_count' => 12,
            'follower_count' => 230,
        ]);

        UserFollow::query()->create([
            'follower_user_id' => $viewer->id,
            'following_user_id' => $author->id,
            'status' => 'accepted',
        ]);

        $post = Post::create([
            'user_id' => $author->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'carousel',
            'feed_type' => 'style',
            'caption' => 'Neutral winter fit',
            'location_name' => 'Tokyo Tower',
            'city' => 'Tokyo',
            'country' => 'JP',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'allow_comments' => true,
            'allow_shares' => true,
            'rating_enabled' => true,
            'like_count' => 1200,
            'comment_count' => 48,
            'share_count' => 14,
            'save_count' => 87,
            'view_count' => 5400,
            'rating_avg' => 4.8,
            'rating_count' => 25,
            'posted_at' => now()->subHour(),
            'published_at' => now()->subHour(),
        ]);

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_url' => 'https://example.com/look-1.jpg',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_url' => 'https://example.com/look-2.jpg',
            'sort_order' => 1,
            'processing_status' => 'ready',
        ]);

        $tag = Tag::create([
            'name' => 'Winter',
            'normalized_name' => 'winter',
            'usage_count' => 1,
        ]);

        PostTag::create([
            'post_id' => $post->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
        ]);

        Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'Love this look',
            'status' => 'active',
            'like_count' => 6,
            'reply_count' => 1,
        ]);

        PostLike::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
        ]);

        SavedPost::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
        ]);

        PostRating::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
            'rating_value' => 5,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/home?type=outfit&page=1');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('type', 'outfit')
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('feed.0.id', $post->id)
            ->assertJsonPath('feed.0.author.username', 'kai_vibe')
            ->assertJsonPath('feed.0.author.is_following', true)
            ->assertJsonPath('feed.0.primary_media_type', 'image')
            ->assertJsonPath('feed.0.has_multiple_media', true)
            ->assertJsonPath('feed.0.media_count', 2)
            ->assertJsonPath('feed.0.engagement.like_count', 1200)
            ->assertJsonPath('feed.0.engagement.comment_count', 48)
            ->assertJsonPath('feed.0.engagement.rating_avg', 4.8)
            ->assertJsonPath('feed.0.viewer_state.is_liked', true)
            ->assertJsonPath('feed.0.viewer_state.is_saved', true)
            ->assertJsonPath('feed.0.viewer_state.viewer_rating', 5)
            ->assertJsonPath('feed.0.tags.0.name', 'Winter');
    }

    public function test_home_feed_uses_fixed_page_size_of_ten(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            Post::create([
                'user_id' => $author->id,
                'post_type' => 'outfit',
                'content_type' => 'fashion',
                'media_kind' => 'image',
                'feed_type' => 'style',
                'caption' => 'Post '.$i,
                'visibility' => 'public',
                'status' => 'published',
                'moderation_status' => 'clean',
                'posted_at' => now()->subMinutes($i),
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/home?page=1');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'feed')
            ->assertJsonPath('type', 'outfit')
            ->assertJsonPath('pagination.total', 12)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.has_more_pages', true);
    }

    public function test_home_feed_can_filter_food_posts_using_type_parameter(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();

        $foodPost = Post::create([
            'user_id' => $author->id,
            'post_type' => 'food',
            'content_type' => 'food',
            'media_kind' => 'image',
            'feed_type' => 'bite',
            'caption' => 'Best ramen in town',
            'dish_name' => 'Spicy Ramen',
            'restaurant' => 'Noodle House',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        Post::create([
            'user_id' => $author->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Should not appear',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'posted_at' => now()->subMinute(),
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/home?type=food&page=1');

        $response
            ->assertOk()
            ->assertJsonPath('type', 'food')
            ->assertJsonCount(1, 'feed')
            ->assertJsonPath('feed.0.id', $foodPost->id)
            ->assertJsonPath('feed.0.post_type', 'food')
            ->assertJsonPath('feed.0.dish_name', 'Spicy Ramen')
            ->assertJsonPath('feed.0.restaurant', 'Noodle House');
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $token = str_repeat('f', 80);

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
