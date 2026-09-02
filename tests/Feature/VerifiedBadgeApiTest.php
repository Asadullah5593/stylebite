<?php

namespace Tests\Feature;

use App\Models\ProfileBadge;
use App\Models\User;
use App\Models\UserSession;
use App\Services\VerifiedBadgeSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blue tick had two sources of truth: admins wrote a verified_user row in
 * profile_badges, the API read profiles.is_verified_badge, and nothing joined
 * them. Granting the badge from the dashboard left the app showing the user as
 * unverified. These pin the two together.
 */
class VerifiedBadgeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_granting_the_badge_from_the_admin_panel_shows_in_the_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        [$user, $token] = $this->userWithToken();

        $this->getJson('/api/profile/me/overview', $this->headers($token))
            ->assertOk()
            ->assertJsonPath('user.is_verified_badge', false);

        $this->actingAs($admin)
            ->patch(route('admin.users.badge.verified', $user))
            ->assertRedirect();

        $this->getJson('/api/profile/me/overview', $this->headers($token))
            ->assertOk()
            ->assertJsonPath('user.is_verified_badge', true);
    }

    public function test_revoking_the_badge_clears_it_in_the_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        [$user, $token] = $this->userWithToken();

        $this->actingAs($admin)->patch(route('admin.users.badge.verified', $user));
        $this->actingAs($admin)->patch(route('admin.users.badge.verified', $user)); // toggle off

        $this->assertDatabaseMissing('profile_badges', [
            'user_id' => $user->id, 'badge_key' => 'verified_user',
        ]);

        $this->getJson('/api/profile/me/overview', $this->headers($token))
            ->assertOk()
            ->assertJsonPath('user.is_verified_badge', false);
    }

    public function test_the_generic_badge_catalog_route_also_keeps_the_flag_in_step(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        [$user, $token] = $this->userWithToken();

        $this->actingAs($admin)->patch(route('admin.users.badges.update', $user), [
            'badge_key' => 'verified_user',
            'action' => 'attach',
        ])->assertRedirect();

        // Wired via model events, so every write path syncs — not just the toggle.
        $this->assertTrue((bool) $user->fresh()->profile->is_verified_badge);
    }

    public function test_a_different_badge_does_not_grant_the_tick(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        [$user, $token] = $this->userWithToken();

        $this->actingAs($admin)->patch(route('admin.users.badges.update', $user), [
            'badge_key' => 'top_creator',
            'action' => 'attach',
        ])->assertRedirect();

        $this->getJson('/api/profile/me/overview', $this->headers($token))
            ->assertOk()
            ->assertJsonPath('user.is_verified_badge', false);
    }

    public function test_users_can_no_longer_verify_themselves(): void
    {
        [, $token] = $this->userWithToken();

        // The self-service endpoint is gone: the badge is an admin decision.
        $this->postJson('/api/profile/me/verify', [], $this->headers($token))
            ->assertNotFound();
    }

    public function test_backfill_repairs_rows_that_drifted_before_the_fix(): void
    {
        [$user] = $this->userWithToken();

        // Simulate the old broken state: badge present, column never written.
        ProfileBadge::withoutEvents(fn () => ProfileBadge::create([
            'user_id' => $user->id,
            'badge_key' => 'verified_user',
            'title' => 'Verified User',
            'status' => 'earned',
            'earned_at' => now(),
        ]));
        $user->profile()->firstOrCreate(['user_id' => $user->id])
            ->update(['is_verified_badge' => false]);

        app(VerifiedBadgeSynchronizer::class)->syncAll();

        $this->assertTrue((bool) $user->fresh()->profile->is_verified_badge);
    }

    private function userWithToken(): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $token = 'vb'.hash('sha256', 'vb-'.$user->id);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'web',
            'last_seen_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }

    private function headers(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }
}
