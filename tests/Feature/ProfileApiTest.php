<?php

namespace Tests\Feature;

use App\Models\EarningsWallet;
use App\Models\Memory;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Profile;
use App\Models\ProfileBadge;
use App\Models\SavedPost;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_me_returns_complete_summary_and_outfit_tab(): void
    {
        [$user, $token] = $this->authenticatedUser();

        Profile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => 'Elara Vibe',
                'bio' => 'Digital Fashion Enthusiast',
                'city' => 'Karachi',
                'country' => 'Pakistan',
                'vibe_count' => 12400,
                'following_count' => 842,
                'style_points' => 45200,
                'current_streak_days' => 14,
                'current_streak_label' => 'LEVELING UP',
                'contest_wins' => 8,
                'contest_entries' => 10,
                'battle_wins' => 24,
                'battle_rank_label' => 'PRO DIVISION CANDIDATE',
                'is_verified_badge' => true,
            ]
        );

        EarningsWallet::create([
            'user_id' => $user->id,
            'available_balance' => 250.75,
            'pending_balance' => 50.25,
            'lifetime_earned' => 1200.00,
            'lifetime_withdrawn' => 400.00,
        ]);

        ProfileBadge::create([
            'user_id' => $user->id,
            'badge_key' => 'contest_winner',
            'title' => 'Contest Winner',
            'icon_key' => 'medal',
            'status' => 'earned',
            'sort_order' => 1,
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Streetwear look',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_url' => 'posts/1/look.jpg',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        Memory::create([
            'user_id' => $user->id,
            'title' => 'Trip',
            'memory_date' => now()->toDateString(),
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me?tab=outfits&page=1');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('profile.username', $user->username)
            ->assertJsonPath('profile.display_name', 'Elara Vibe')
            ->assertJsonPath('profile.stats.vibe_count', 12400)
            ->assertJsonPath('profile.stats.vibe_flow', 842)
            ->assertJsonPath('profile.stats.style_points', 45200)
            ->assertJsonPath('profile.streak.days', 14)
            ->assertJsonPath('profile.contest.wins', 8)
            ->assertJsonPath('profile.contest.entries', 10)
            ->assertJsonPath('profile.battle.wins', 24)
            ->assertJsonPath('profile.earnings.available_balance', 250.75)
            ->assertJsonPath('active_tab', 'outfits')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('pagination.per_page', 10);
    }

    public function test_profile_saved_tab_is_only_available_for_profile_owner(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $otherUser = User::factory()->create();

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/profiles/'.$otherUser->username.'?tab=saved&page=1');

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'Saved posts are only available for the profile owner.');
    }

    public function test_profile_saved_and_memories_tabs_return_paginated_items(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $otherPost = Post::create([
            'user_id' => $user->id,
            'post_type' => 'food',
            'content_type' => 'food',
            'media_kind' => 'image',
            'feed_type' => 'bite',
            'caption' => 'Food post',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        SavedPost::create([
            'user_id' => $user->id,
            'post_id' => $otherPost->id,
        ]);

        Memory::create([
            'user_id' => $user->id,
            'title' => 'Memory Title',
            'memory_date' => now()->toDateString(),
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $savedResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me?tab=saved&page=1');

        $savedResponse
            ->assertOk()
            ->assertJsonPath('active_tab', 'saved')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('pagination.per_page', 10);

        $memoryResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me?tab=memories&page=1');

        $memoryResponse
            ->assertOk()
            ->assertJsonPath('active_tab', 'memories')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('pagination.per_page', 10);
    }

    public function test_profile_overview_returns_stats_and_paginated_outfit_food_and_saved_posts(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $user->forceFill([
            'phone_country_code' => '+92',
            'phone_number' => '3001234567',
            'locale' => 'en',
            'timezone' => 'Asia/Karachi',
        ])->save();

        Profile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => 'Elara Vibe',
                'headline' => 'Digital Fashion Enthusiast',
                'website_url' => 'https://example.com',
                'city' => 'Karachi',
                'country' => 'Pakistan',
                'gender' => 'female',
                'birth_date' => '1998-02-10',
                'visibility' => 'public',
                'is_private' => false,
                'following_count' => 25,
                'follower_count' => 80,
            ]
        );

        $outfitPost = Post::create([
            'user_id' => $user->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Outfit post',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'like_count' => 7,
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        $foodPost = Post::create([
            'user_id' => $user->id,
            'post_type' => 'food',
            'content_type' => 'food',
            'media_kind' => 'image',
            'feed_type' => 'bite',
            'caption' => 'Food post',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'like_count' => 5,
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        SavedPost::create([
            'user_id' => $user->id,
            'post_id' => $foodPost->id,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/overview');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('user.username', $user->username)
            ->assertJsonPath('user.headline', 'Digital Fashion Enthusiast')
            ->assertJsonPath('user.phone_country_code', '+92')
            ->assertJsonPath('user.phone_number', '3001234567')
            ->assertJsonPath('user.city', 'Karachi')
            ->assertJsonPath('user.following_count', 25)
            ->assertJsonPath('user.follower_count', 80)
            ->assertJsonPath('user.total_post_likes', 12)
            ->assertJsonCount(1, 'outfit_posts')
            ->assertJsonCount(1, 'food_posts')
            ->assertJsonCount(1, 'saved_posts')
            ->assertJsonPath('outfit_pagination.per_page', 10)
            ->assertJsonPath('food_pagination.per_page', 10)
            ->assertJsonPath('saved_pagination.per_page', 10)
            ->assertJsonPath('outfit_posts.0.id', $outfitPost->id)
            ->assertJsonPath('food_posts.0.id', $foodPost->id)
            ->assertJsonPath('saved_posts.0.post.id', $foodPost->id);
    }

    public function test_profile_overview_can_update_headline(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders($this->headers($token))
            ->patchJson('/api/profile/me/overview', [
                'headline' => 'Digital Fashion Enthusiast',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('user.headline', 'Digital Fashion Enthusiast');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'headline' => 'Digital Fashion Enthusiast',
        ]);
    }

    public function test_profile_update_updates_allowed_fields_in_user_and_profile_tables(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders($this->headers($token))
            ->patchJson('/api/profile/me', [
                'username' => 'asif_younas',
                'full_name' => 'Asif Younas',
                'phone_country_code' => '+92',
                'phone_number' => '3011234567',
                'locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'display_name' => 'Asif',
                'headline' => 'Digital Fashion Enthusiast',
                'bio' => 'Creator profile',
                'website_url' => 'https://example.com',
                'city' => 'Karachi',
                'country' => 'Pakistan',
                'gender' => 'male',
                'birth_date' => '1995-01-15',
                'visibility' => 'public',
                'is_private' => false,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('user.username', 'asif_younas')
            ->assertJsonPath('user.full_name', 'Asif Younas')
            ->assertJsonPath('user.display_name', 'Asif')
            ->assertJsonPath('user.headline', 'Digital Fashion Enthusiast')
            ->assertJsonPath('user.city', 'Karachi');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'asif_younas',
            'full_name' => 'Asif Younas',
            'phone_country_code' => '+92',
            'phone_number' => '3011234567',
            'timezone' => 'Asia/Karachi',
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'display_name' => 'Asif',
            'headline' => 'Digital Fashion Enthusiast',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'gender' => 'male',
        ]);
    }

    public function test_profile_verify_grants_verification_badge_to_verified_user(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $response = $this->withHeaders($this->headers($token))
            ->postJson('/api/profile/me/verify');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('user.is_verified_badge', true);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'is_verified_badge' => true,
        ]);

        $this->assertDatabaseHas('profile_badges', [
            'user_id' => $user->id,
            'badge_key' => 'verified_user',
            'title' => 'Verified User',
        ]);
    }

    public function test_public_profile_overview_returns_any_user_complete_profile_without_saved_posts_for_others(): void
    {
        [$viewer, $token] = $this->authenticatedUser();
        $otherUser = User::factory()->create([
            'username' => 'other_creator',
            'full_name' => 'Other Creator',
        ]);

        Profile::create([
            'user_id' => $otherUser->id,
            'display_name' => 'Other Creator',
            'headline' => 'Fashion Creator',
            'bio' => 'Public creator profile',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'visibility' => 'public',
            'is_private' => false,
            'follower_count' => 20,
            'following_count' => 15,
            'post_count' => 2,
            'style_points' => 150,
            'is_verified_badge' => true,
        ]);

        $outfitPost = Post::create([
            'user_id' => $otherUser->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Other outfit',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'like_count' => 4,
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        $foodPost = Post::create([
            'user_id' => $otherUser->id,
            'post_type' => 'food',
            'content_type' => 'food',
            'media_kind' => 'image',
            'feed_type' => 'bite',
            'caption' => 'Other food',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'like_count' => 6,
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        SavedPost::create([
            'user_id' => $otherUser->id,
            'post_id' => $foodPost->id,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/profiles/'.$otherUser->username.'/overview');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('user.username', 'other_creator')
            ->assertJsonPath('user.headline', 'Fashion Creator')
            ->assertJsonPath('user.total_post_likes', 10)
            ->assertJsonPath('user.saved_post_count', null)
            ->assertJsonPath('user.is_self', false)
            ->assertJsonCount(1, 'outfit_posts')
            ->assertJsonCount(1, 'food_posts')
            ->assertJsonCount(0, 'saved_posts')
            ->assertJsonPath('outfit_posts.0.id', $outfitPost->id)
            ->assertJsonPath('food_posts.0.id', $foodPost->id)
            ->assertJsonPath('saved_pagination.total', 0);
    }

    public function test_username_availability_endpoint_checks_real_time_availability(): void
    {
        [$user, $token] = $this->authenticatedUser();

        User::factory()->create([
            'username' => 'taken_name',
        ]);

        $availableResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/username-availability?username=fresh_name');

        $availableResponse
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('username', 'fresh_name')
            ->assertJsonPath('is_available', true)
            ->assertJsonPath('is_current_username', false);

        $takenResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/username-availability?username=taken_name');

        $takenResponse
            ->assertOk()
            ->assertJsonPath('is_available', false)
            ->assertJsonPath('is_current_username', false);

        $ownResponse = $this->withHeaders($this->headers($token))
            ->getJson('/api/profile/me/username-availability?username=elara_vibe');

        $ownResponse
            ->assertOk()
            ->assertJsonPath('is_available', true)
            ->assertJsonPath('is_current_username', true);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create([
            'username' => 'elara_vibe',
        ]);
        $token = str_repeat('u', 80);

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
