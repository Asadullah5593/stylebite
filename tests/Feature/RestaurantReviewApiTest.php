<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\RestaurantReview;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_and_list_restaurant_reviews(): void
    {
        [$user, $token] = $this->authenticatedUser('junaid');
        $author = User::factory()->create(['username' => 'foodie_ali']);
        $post = $this->createFoodPost($author->id, 'Tokyo Bowl');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/restaurants/reviews', [
                'restaurant' => 'Tokyo Bowl',
                'food_rating' => 4.5,
                'service_rating' => 4,
                'staff_rating' => 5,
                'ambience_rating' => 3.5,
                'post_id' => $post->id,
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('review.username', 'junaid')
            ->assertJsonPath('review.restaurant', 'Tokyo Bowl')
            ->assertJsonPath('review.food_rating', 4.5)
            ->assertJsonPath('review.post_id', $post->id);

        $this->assertDatabaseHas('restaurant_reviews', [
            'user_id' => $user->id,
            'restaurant' => 'Tokyo Bowl',
            'post_id' => $post->id,
        ]);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/restaurants/reviews?restaurant=Tokyo%20Bowl&q=Tokyo%20Bowl')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('reviews.0.username', 'junaid')
            ->assertJsonPath('reviews.0.food_rating', 4.5)
            ->assertJsonPath('summary.count', 1);
    }

    public function test_restaurant_review_is_upserted_per_user_and_restaurant(): void
    {
        [$user, $token] = $this->authenticatedUser('junaid');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/restaurants/reviews', [
                'restaurant' => 'Ramen House',
                'food_rating' => 3,
                'service_rating' => 3,
                'staff_rating' => 3,
                'ambience_rating' => 3,
            ])
            ->assertOk();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/restaurants/reviews', [
                'restaurant' => 'Ramen House Restaurant',
                'food_rating' => 5,
                'service_rating' => 4,
                'staff_rating' => 4,
                'ambience_rating' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('review.food_rating', 5);

        $this->assertSame(1, RestaurantReview::query()->where('user_id', $user->id)->count());
        $this->assertSame(5.0, (float) RestaurantReview::query()->where('user_id', $user->id)->value('food_rating'));
    }

    public function test_food_review_endpoints_use_the_bite_post_restaurant(): void
    {
        [$user, $token] = $this->authenticatedUser('bite_fan');
        $author = User::factory()->create(['username' => 'chef']);
        $post = $this->createFoodPost($author->id, 'Monal');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/food-review', [
                'food_rating' => 5,
                'service_rating' => 4,
                'staff_rating' => 4,
                'ambience_rating' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('review.restaurant', 'Monal')
            ->assertJsonPath('review.username', 'bite_fan');

        $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/posts/'.$post->id.'/food-reviews')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('reviews.0.username', 'bite_fan')
            ->assertJsonPath('summary.count', 1);
    }

    public function test_unknown_local_post_id_is_ignored_on_restaurant_review(): void
    {
        [$user, $token] = $this->authenticatedUser('junaid');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/restaurants/reviews', [
                'restaurant' => 'Live Cafe',
                'food_rating' => 4,
                'service_rating' => 4,
                'staff_rating' => 4,
                'ambience_rating' => 4,
                'post_id' => 999999,
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertDatabaseHas('restaurant_reviews', [
            'user_id' => $user->id,
            'restaurant' => 'Live Cafe',
            'post_id' => null,
        ]);
    }

    public function test_outfit_posts_cannot_receive_food_reviews(): void
    {
        [, $token] = $this->authenticatedUser('junaid');
        $author = User::factory()->create();
        $post = Post::create([
            'user_id' => $author->id,
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
        ]);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/feed/posts/'.$post->id.'/food-review', [
                'food_rating' => 5,
                'service_rating' => 5,
                'staff_rating' => 5,
                'ambience_rating' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0);
    }

    public function test_reviews_can_be_listed_without_a_local_session(): void
    {
        $author = User::factory()->create(['username' => 'foodie_ali']);
        $this->createFoodPost($author->id, 'Tokyo Bowl');

        $this->getJson('/api/restaurants/reviews?restaurant=Tokyo%20Bowl')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('summary.count', 0);

        $this->postJson('/api/restaurants/reviews', [
            'restaurant' => 'Tokyo Bowl',
            'food_rating' => 4,
            'service_rating' => 4,
            'staff_rating' => 4,
            'ambience_rating' => 4,
        ])->assertUnauthorized();
    }

    private function createFoodPost(int $userId, string $restaurant): Post
    {
        $post = Post::create([
            'user_id' => $userId,
            'post_type' => 'food',
            'content_type' => 'food',
            'media_kind' => 'image',
            'feed_type' => 'bite',
            'caption' => 'Lunch set',
            'dish_name' => 'Karahi',
            'restaurant' => $restaurant,
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'food_rating' => 5,
            'service_rating' => 4,
            'staff_rating' => 4,
            'ambience_rating' => 5,
            'posted_at' => now(),
            'published_at' => now(),
        ]);

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

    private function authenticatedUser(string $username = 'reviewer'): array
    {
        $user = User::factory()->create([
            'username' => $username,
            'status' => 'active',
        ]);
        $token = str_repeat('r', 80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'device_id' => 'android-device-1',
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
