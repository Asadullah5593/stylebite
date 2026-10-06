<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTag;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndHashtagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_posts_matches_caption_restaurant_and_tag(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create(['username' => 'foodie_ali']);

        $captionMatch = $this->createPublishedPost($author->id, [
            'post_type' => 'food',
            'content_type' => 'food',
            'feed_type' => 'bite',
            'caption' => 'Best spicy ramen this week',
            'dish_name' => 'Shoyu',
            'restaurant' => 'Tokyo Bowl',
        ]);

        $restaurantMatch = $this->createPublishedPost($author->id, [
            'post_type' => 'food',
            'content_type' => 'food',
            'feed_type' => 'bite',
            'caption' => 'Lunch set',
            'dish_name' => 'Katsu',
            'restaurant' => 'Ramen House',
        ]);

        $tagMatch = $this->createPublishedPost($author->id, [
            'post_type' => 'food',
            'content_type' => 'food',
            'feed_type' => 'bite',
            'caption' => 'Night market finds',
            'dish_name' => 'Skewer',
            'restaurant' => 'Street Cart',
        ]);
        $this->attachTag($tagMatch, 'Ramen');

        $unrelated = $this->createPublishedPost($author->id, [
            'post_type' => 'food',
            'content_type' => 'food',
            'feed_type' => 'bite',
            'caption' => 'Chocolate cake',
            'dish_name' => 'Cake',
            'restaurant' => 'Bakehouse',
        ]);

        $outfitMatch = $this->createPublishedPost($author->id, [
            'caption' => 'Ramen print shirt',
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/search/posts?type=food&q=ramen&page=1&per_page=18');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('type', 'food')
            ->assertJsonPath('search_query', 'ramen')
            ->assertJsonPath('pagination.per_page', 18);

        $ids = collect($response->json('feed'))->pluck('id');

        $this->assertTrue($ids->contains($captionMatch->id));
        $this->assertTrue($ids->contains($restaurantMatch->id));
        $this->assertTrue($ids->contains($tagMatch->id));
        $this->assertFalse($ids->contains($unrelated->id));
        $this->assertFalse($ids->contains($outfitMatch->id));
        $this->assertSame('Ramen', $response->json('feed.0.tags.0.name')
            ?? collect($response->json('feed'))
                ->firstWhere('id', $tagMatch->id)['tags'][0]['name'] ?? null);
    }

    public function test_search_posts_without_query_returns_empty_feed(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $this->createPublishedPost($author->id, ['caption' => 'Visible look']);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/search/posts?type=outfit')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonCount(0, 'feed')
            ->assertJsonPath('search_query', '');
    }

    public function test_hashtag_feed_matches_tag_and_caption_hash(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create(['username' => 'style_maya']);

        $tagged = $this->createPublishedPost($author->id, [
            'caption' => 'City night fit',
        ]);
        $this->attachTag($tagged, 'Summer');

        $captionHashtag = $this->createPublishedPost($author->id, [
            'caption' => 'Golden hour #SuMmEr look',
        ]);

        $other = $this->createPublishedPost($author->id, [
            'caption' => 'Winter layers',
        ]);
        $this->attachTag($other, 'Winter');

        $ownTagged = $this->createPublishedPost($viewer->id, [
            'caption' => 'My own summer post',
        ]);
        $this->attachTag($ownTagged, 'summer');

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/hashtags/Summer?page=1&per_page=18');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('hashtag', 'summer')
            ->assertJsonPath('pagination.per_page', 18);

        $ids = collect($response->json('feed'))->pluck('id');

        $this->assertTrue($ids->contains($tagged->id));
        $this->assertTrue($ids->contains($captionHashtag->id));
        $this->assertTrue($ids->contains($ownTagged->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_feed_tags_alias_returns_the_same_hashtag_results(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $author = User::factory()->create();
        $post = $this->createPublishedPost($author->id, ['caption' => 'Denim day']);
        $this->attachTag($post, 'denim');

        $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/tags/%23Denim')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('hashtag', 'denim')
            ->assertJsonPath('feed.0.id', $post->id);
    }

    public function test_search_and_hashtag_are_available_without_a_local_session(): void
    {
        $this->getJson('/api/search/posts?type=outfit&q=summer')
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->getJson('/api/feed/hashtags/summer')
            ->assertOk()
            ->assertJsonPath('status_code', 1);
    }

    private function createPublishedPost(int $userId, array $overrides = []): Post
    {
        $post = Post::create(array_merge([
            'user_id' => $userId,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Look',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'posted_at' => now(),
            'published_at' => now(),
        ], $overrides));

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_url' => 'https://example.com/post-'.$post->id.'.jpg',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        return $post;
    }

    private function attachTag(Post $post, string $name): Tag
    {
        $tag = Tag::query()->firstOrCreate(
            ['normalized_name' => strtolower($name)],
            ['name' => $name, 'usage_count' => 1],
        );

        PostTag::create([
            'post_id' => $post->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
        ]);

        return $tag;
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'display_name' => $user->full_name,
            'visibility' => 'public',
            'is_private' => false,
        ]);

        $token = str_repeat('s', 80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
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
