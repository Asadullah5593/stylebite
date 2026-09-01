<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserSession;
use App\Services\FollowCountSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * profiles.follower_count is a cache of user_follows, and it used to be
 * refreshed on follow and unfollow and nowhere else. Blocking someone removes
 * the follow both ways, and deleting an account leaves its follow rows behind —
 * neither touched the cache, so profiles reported followers that no account
 * matched.
 */
class FollowCountIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_block_drops_the_follower_count_on_both_sides(): void
    {
        [$alice, $aliceToken] = $this->authenticatedUser();
        [$bob, $bobToken] = $this->authenticatedUser();

        $this->follow($aliceToken, $bob);
        $this->follow($bobToken, $alice);
        $this->assertSame(1, $this->followerCount($bob));
        $this->assertSame(1, $this->followerCount($alice));

        $this->postJson("/api/profiles/{$bob->username}/block", [], $this->headers($aliceToken))
            ->assertOk();

        // The follows are gone, so the counts must be too.
        $this->assertSame(0, $this->followerCount($bob), 'blocked user kept a phantom follower');
        $this->assertSame(0, $this->followerCount($alice), 'blocker kept a phantom follower');
    }

    public function test_deleting_an_account_removes_it_from_everyone_it_followed(): void
    {
        [$alice] = $this->authenticatedUser();
        [$bob, $bobToken] = $this->authenticatedUser();

        $this->follow($bobToken, $alice);
        $this->assertSame(1, $this->followerCount($alice));

        // Soft-delete Bob the way the admin panel does, then resync.
        $bob->delete();
        app(FollowCountSynchronizer::class)->syncWithCounterparts($bob->id);

        $this->assertSame(0, $this->followerCount($alice), 'a deleted account still counted as a follower');
    }

    public function test_restoring_an_account_puts_the_follower_back(): void
    {
        [$alice] = $this->authenticatedUser();
        [$bob, $bobToken] = $this->authenticatedUser();

        $this->follow($bobToken, $alice);
        $bob->delete();
        app(FollowCountSynchronizer::class)->syncWithCounterparts($bob->id);
        $this->assertSame(0, $this->followerCount($alice));

        $bob->restore();
        app(FollowCountSynchronizer::class)->syncWithCounterparts($bob->id);
        $this->assertSame(1, $this->followerCount($alice));
    }

    public function test_a_counter_left_behind_by_emptied_tables_is_corrected(): void
    {
        // Exactly what happened in production: the follow rows were cleared out
        // from under the cache, which kept reporting its last value forever
        // because nothing recounts unless somebody follows or unfollows.
        [$alice] = $this->authenticatedUser();
        Profile::query()->updateOrCreate(['user_id' => $alice->id], ['follower_count' => 2]);
        UserFollow::query()->delete();

        app(FollowCountSynchronizer::class)->sync($alice->id);

        $this->assertSame(0, $this->followerCount($alice));
    }

    public function test_the_overview_endpoint_reports_the_corrected_number(): void
    {
        [$alice, $aliceToken] = $this->authenticatedUser();
        Profile::query()->updateOrCreate(['user_id' => $alice->id], ['follower_count' => 2]);

        app(FollowCountSynchronizer::class)->sync($alice->id);

        $this->getJson('/api/profile/me/overview', $this->headers($aliceToken))
            ->assertOk()
            ->assertJsonPath('user.follower_count', 0);
    }

    public function test_following_count_ignores_accounts_that_no_longer_exist(): void
    {
        [$alice, $aliceToken] = $this->authenticatedUser();
        [$bob] = $this->authenticatedUser();

        $this->follow($aliceToken, $bob);
        $this->assertSame(1, $this->followingCount($alice));

        $bob->delete();
        app(FollowCountSynchronizer::class)->sync($alice->id);

        $this->assertSame(0, $this->followingCount($alice), 'still following a deleted account');
    }

    // — helpers —

    private function follow(string $token, User $target): void
    {
        $this->postJson("/api/profiles/{$target->username}/follow", [], $this->headers($token))
            ->assertOk();
    }

    private function followerCount(User $user): int
    {
        return (int) Profile::query()->where('user_id', $user->id)->value('follower_count');
    }

    private function followingCount(User $user): int
    {
        return (int) Profile::query()->where('user_id', $user->id)->value('following_count');
    }

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
}
