<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Profile;
use App\Models\SavedPost;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileOverviewSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_without_page_parameters_returns_every_section(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $this->publish($user, 'outfit', 4);
        $this->publish($user, 'outfit', 2);
        $food = $this->publish($user, 'food', 7);
        SavedPost::query()->create([
            'user_id' => $user->id,
            'post_id' => $food->id,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/overview');

        $response->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonCount(2, 'outfit_posts')
            ->assertJsonCount(1, 'food_posts')
            ->assertJsonCount(1, 'saved_posts')
            ->assertJsonPath('outfit_pagination.total', 2)
            ->assertJsonPath('outfit_pagination.last_page', 1)
            ->assertJsonPath('outfit_pagination.has_more_pages', false)
            ->assertJsonPath('food_pagination.total', 1)
            ->assertJsonPath('saved_pagination.total', 1)
            ->assertJsonPath('user.memory_count', 0)
            ->assertJsonPath('user.saved_post_count', 1)
            ->assertJsonPath('user.total_post_likes', 13)
            ->assertJsonPath('user.contest_wins', 0)
            ->assertJsonPath('user.contest_entries', 0);

        $withPageSize = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/overview?per_page=10');
        $withPageSize->assertOk()
            ->assertJsonCount(2, 'outfit_posts')
            ->assertJsonCount(1, 'food_posts')
            ->assertJsonCount(1, 'saved_posts')
            ->assertJsonPath('outfit_pagination.total', 2)
            ->assertJsonPath('outfit_pagination.last_page', 1);
    }

    public function test_outfit_page_request_does_not_build_the_other_sections(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $this->publish($user, 'outfit', 3);
        $this->publish($user, 'outfit', 5);
        $this->publish($user, 'food', 9);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/overview?outfit_page=1&per_page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'outfit_posts')
            ->assertJsonCount(0, 'food_posts')
            ->assertJsonCount(0, 'saved_posts')
            ->assertJsonPath('outfit_pagination.total', 2)
            ->assertJsonPath('outfit_pagination.per_page', 1)
            ->assertJsonPath('outfit_pagination.last_page', 2)
            ->assertJsonPath('outfit_pagination.has_more_pages', true)
            ->assertJsonPath('food_pagination.total', 0)
            ->assertJsonPath('food_pagination.has_more_pages', false)
            ->assertJsonPath('user.total_post_likes', 17);
    }

    public function test_food_and_saved_requests_load_only_that_section(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $food = $this->publish($user, 'food', 1);
        $this->publish($user, 'outfit', 8);
        SavedPost::query()->create([
            'user_id' => $user->id,
            'post_id' => $food->id,
        ]);

        $foodResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/overview?food_page=1');
        $foodResponse->assertOk()
            ->assertJsonCount(1, 'food_posts')
            ->assertJsonCount(0, 'outfit_posts')
            ->assertJsonPath('food_posts.0.post_type', 'food');

        $savedResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/overview?saved_page=1');
        $savedResponse->assertOk()
            ->assertJsonCount(1, 'saved_posts')
            ->assertJsonCount(0, 'outfit_posts')
            ->assertJsonCount(0, 'food_posts')
            ->assertJsonPath('saved_pagination.total', 1)
            ->assertJsonPath('saved_posts.0.post.id', $food->id);
    }

    public function test_legacy_outfits_page_alias_still_selects_the_outfit_section(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $this->publish($user, 'outfit', 1);
        $this->publish($user, 'food', 1);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/overview?outfits_page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'outfit_posts')
            ->assertJsonCount(0, 'food_posts');
    }

    public function test_public_overview_hides_saved_posts_and_keeps_section_selection(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $owner = User::factory()->create(['username' => 'owner_name']);
        Profile::query()->create([
            'user_id' => $owner->id,
            'display_name' => 'Owner',
        ]);
        $this->publish($owner, 'outfit', 2);
        $food = $this->publish($owner, 'food', 6);
        SavedPost::query()->create([
            'user_id' => $owner->id,
            'post_id' => $food->id,
        ]);

        $all = $this->withHeaders($this->headers($token))
            ->getJson('/api/profiles/owner_name/overview');
        $all->assertOk()
            ->assertJsonCount(1, 'outfit_posts')
            ->assertJsonCount(1, 'food_posts')
            ->assertJsonCount(0, 'saved_posts')
            ->assertJsonPath('user.saved_post_count', null)
            ->assertJsonPath('user.total_post_likes', 8)
            ->assertJsonPath('user.relationship.is_following', false);

        $foodOnly = $this->withHeaders($this->headers($token))
            ->getJson('/api/profiles/owner_name/overview?food_page=1');
        $foodOnly->assertOk()
            ->assertJsonCount(0, 'outfit_posts')
            ->assertJsonCount(1, 'food_posts')
            ->assertJsonPath('food_pagination.total', 1);

        $this->assertNotSame($viewer->id, $owner->id);
    }

    private function publish(User $user, string $type, int $likes): Post
    {
        return Post::query()->create([
            'user_id' => $user->id,
            'post_type' => $type,
            'content_type' => $type === 'food' ? 'food' : 'fashion',
            'media_kind' => 'image',
            'feed_type' => $type === 'food' ? 'bite' : 'style',
            'caption' => $type.' look',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'like_count' => $likes,
            'posted_at' => now(),
            'published_at' => now(),
        ]);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        Profile::query()->create([
            'user_id' => $user->id,
            'display_name' => $user->username,
        ]);
        $token = str_repeat('a', 80);
        UserSession::query()->create([
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
