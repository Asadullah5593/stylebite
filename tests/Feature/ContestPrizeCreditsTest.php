<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\ContestPlace;
use App\Models\ContestSubmission;
use App\Models\EarningTransaction;
use App\Models\EarningsWallet;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSession;
use App\Support\ContestResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContestPrizeCreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finishing_a_contest_creates_pending_prize_not_available(): void
    {
        [$winner] = $this->authenticatedUser('winner_one');
        [$other] = $this->authenticatedUser('other_one');

        $contest = $this->makeContest();
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 5000,
            'winner_user_id' => null,
        ]);

        $this->submit($contest, $winner, 5, 350, 1);
        $this->submit($contest, $other, 3, 210, 2);

        ContestResults::finish($contest->fresh());

        $place = ContestPlace::query()->where('contest_id', $contest->id)->where('place', 1)->first();
        $this->assertSame((int) $winner->id, (int) $place->winner_user_id);

        $credit = EarningTransaction::query()
            ->where('user_id', $winner->id)
            ->where('source_type', 'contest_reward')
            ->where('source_id', $place->id)
            ->first();
        $this->assertNotNull($credit);
        $this->assertSame('pending', $credit->status);
        $this->assertSame(5000.0, (float) $credit->amount);

        $wallet = EarningsWallet::query()->where('user_id', $winner->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame(5000.0, (float) $wallet->pending_balance);
        $this->assertSame(0.0, (float) $wallet->available_balance);
        $this->assertSame(0.0, (float) $wallet->lifetime_earned);

        ContestResults::finish($contest->fresh());
        $this->assertSame(1, EarningTransaction::query()
            ->where('source_type', 'contest_reward')
            ->where('source_id', $place->id)
            ->count());
        $this->assertSame(5000.0, (float) $wallet->fresh()->pending_balance);
        $this->assertSame(0.0, (float) $wallet->fresh()->available_balance);

        $this->assertSame(0, EarningTransaction::query()
            ->where('user_id', $other->id)
            ->where('source_type', 'contest_reward')
            ->count());
    }

    public function test_earnings_overview_shows_pending_prize_and_blocks_withdrawal(): void
    {
        [$winner, $token] = $this->authenticatedUser('winner_two');

        $contest = $this->makeContest();
        $place = ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 5000,
            'winner_user_id' => $winner->id,
        ]);
        $this->submit($contest, $winner, 4.5, 315, 1);
        ContestResults::finish($contest->fresh());

        $this->withHeaders($this->headers($token))
            ->getJson('/api/earnings/overview')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('summary.available_balance', 0)
            ->assertJsonPath('summary.pending_balance', 5000)
            ->assertJsonPath('earning_breakdown_preview.0.amount', 5000)
            ->assertJsonPath('earning_breakdown_preview.0.status', 'pending')
            ->assertJsonPath('earning_breakdown_preview.0.amount_direction', 'pending_approval');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/earnings/withdrawals', [
                'amount' => 100,
                'method' => 'bank_transfer',
                'account_ref' => 'TEST-ACC-1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0);

        $this->assertSame(
            1,
            EarningTransaction::query()
                ->where('source_type', 'contest_reward')
                ->where('source_id', $place->id)
                ->count()
        );
    }

    public function test_admin_approval_moves_pending_to_available_once(): void
    {
        [$winner] = $this->authenticatedUser('winner_three');
        $admin = $this->makeAdmin('admin_one');

        $contest = $this->makeContest();
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 5000,
            'winner_user_id' => $winner->id,
        ]);
        $this->submit($contest, $winner, 5, 350, 1);
        ContestResults::finish($contest->fresh());

        $transaction = EarningTransaction::query()
            ->where('user_id', $winner->id)
            ->where('source_type', 'contest_reward')
            ->where('status', 'pending')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.earnings.contest_prizes.approve', $transaction))
            ->assertRedirect();

        $wallet = EarningsWallet::query()->where('user_id', $winner->id)->firstOrFail();
        $this->assertSame(0.0, (float) $wallet->pending_balance);
        $this->assertSame(5000.0, (float) $wallet->available_balance);
        $this->assertSame(5000.0, (float) $wallet->lifetime_earned);
        $this->assertSame('completed', $transaction->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.earnings.contest_prizes.approve', $transaction))
            ->assertRedirect();

        $this->assertSame(0.0, (float) $wallet->fresh()->pending_balance);
        $this->assertSame(5000.0, (float) $wallet->fresh()->available_balance);
        $this->assertSame(5000.0, (float) $wallet->fresh()->lifetime_earned);
        $this->assertSame(
            1,
            EarningTransaction::query()
                ->where('source_type', 'contest_reward')
                ->where('source_id', $transaction->source_id)
                ->count()
        );
    }

    public function test_guest_cannot_approve_contest_prize(): void
    {
        [$winner] = $this->authenticatedUser('winner_four');
        $contest = $this->makeContest();
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 1000,
            'winner_user_id' => $winner->id,
        ]);
        $this->submit($contest, $winner, 4, 280, 1);
        ContestResults::finish($contest->fresh());

        $transaction = EarningTransaction::query()
            ->where('source_type', 'contest_reward')
            ->firstOrFail();

        $this->post(route('admin.earnings.contest_prizes.approve', $transaction))
            ->assertRedirect();

        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame(1000.0, (float) EarningsWallet::query()->where('user_id', $winner->id)->value('pending_balance'));
        $this->assertSame(0.0, (float) EarningsWallet::query()->where('user_id', $winner->id)->value('available_balance'));
    }

    public function test_normal_user_cannot_approve_contest_prize(): void
    {
        [$winner] = $this->authenticatedUser('winner_authz');
        [$user] = $this->authenticatedUser('normal_user');

        $contest = $this->makeContest();
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 1200,
            'winner_user_id' => $winner->id,
        ]);
        $this->submit($contest, $winner, 4, 280, 1);
        ContestResults::finish($contest->fresh());

        $transaction = EarningTransaction::query()
            ->where('source_type', 'contest_reward')
            ->where('status', 'pending')
            ->firstOrFail();

        // EnsureAdminUser logs out non-admins and redirects to the admin login.
        $this->actingAs($user)
            ->post(route('admin.earnings.contest_prizes.approve', $transaction))
            ->assertRedirect(route('admin.login'));

        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame(0.0, (float) EarningsWallet::query()->where('user_id', $winner->id)->value('available_balance'));
    }

    public function test_multiple_winners_receive_independent_pending_prizes(): void
    {
        [$first] = $this->authenticatedUser('place_one');
        [$second] = $this->authenticatedUser('place_two');

        $contest = $this->makeContest(['prize_pool' => 8000]);
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 5000,
            'winner_user_id' => null,
        ]);
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 2,
            'prize_title' => '2nd place',
            'prize_amount' => 3000,
            'winner_user_id' => null,
        ]);

        $this->submit($contest, $first, 5, 350, 1);
        $this->submit($contest, $second, 4, 280, 2);
        ContestResults::finish($contest->fresh());

        $this->assertSame(5000.0, (float) EarningsWallet::query()->where('user_id', $first->id)->value('pending_balance'));
        $this->assertSame(3000.0, (float) EarningsWallet::query()->where('user_id', $second->id)->value('pending_balance'));
        $this->assertSame(0.0, (float) EarningsWallet::query()->where('user_id', $first->id)->value('available_balance'));
        $this->assertSame(0.0, (float) EarningsWallet::query()->where('user_id', $second->id)->value('available_balance'));
        $this->assertSame(2, EarningTransaction::query()->where('source_type', 'contest_reward')->where('status', 'pending')->count());
    }

    public function test_concurrent_approvals_do_not_double_credit(): void
    {
        [$winner] = $this->authenticatedUser('winner_race');
        $adminA = $this->makeAdmin('admin_race_a');
        $adminB = $this->makeAdmin('admin_race_b');

        $contest = $this->makeContest();
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 5000,
            'winner_user_id' => $winner->id,
        ]);
        $this->submit($contest, $winner, 5, 350, 1);
        ContestResults::finish($contest->fresh());

        $transaction = EarningTransaction::query()
            ->where('source_type', 'contest_reward')
            ->where('status', 'pending')
            ->firstOrFail();

        $first = ContestResults::approvePendingPrize((int) $transaction->id, (int) $adminA->id);
        $second = ContestResults::approvePendingPrize((int) $transaction->id, (int) $adminB->id);

        $this->assertNotNull($first);
        $this->assertNull($second);

        $wallet = EarningsWallet::query()->where('user_id', $winner->id)->firstOrFail();
        $this->assertSame(0.0, (float) $wallet->pending_balance);
        $this->assertSame(5000.0, (float) $wallet->available_balance);
        $this->assertSame(5000.0, (float) $wallet->lifetime_earned);
        $this->assertSame((int) $adminA->id, (int) ($transaction->fresh()->metadata_json['approved_by_admin_id'] ?? 0));
    }

    public function test_other_earnings_preserved_when_contest_prize_pending(): void
    {
        [$winner] = $this->authenticatedUser('winner_preserve');
        $wallet = EarningsWallet::query()->create([
            'user_id' => $winner->id,
            'currency_code' => 'PKR',
            'available_balance' => 250,
            'pending_balance' => 0,
            'lifetime_earned' => 250,
            'lifetime_withdrawn' => 0,
        ]);

        $contest = $this->makeContest();
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 5000,
            'winner_user_id' => $winner->id,
        ]);
        $this->submit($contest, $winner, 5, 350, 1);
        ContestResults::finish($contest->fresh());

        $wallet->refresh();
        $this->assertSame(250.0, (float) $wallet->available_balance);
        $this->assertSame(5000.0, (float) $wallet->pending_balance);
        $this->assertSame(250.0, (float) $wallet->lifetime_earned);
    }

    public function test_missing_prize_report_lists_uncradited_places_without_writing(): void
    {
        [$winner] = $this->authenticatedUser('winner_five');
        $contest = $this->makeContest(['status' => 'completed']);
        ContestPlace::query()->create([
            'contest_id' => $contest->id,
            'place' => 1,
            'prize_title' => '1st place',
            'prize_amount' => 900,
            'winner_user_id' => $winner->id,
        ]);

        $report = ContestResults::missingPrizeCredits((int) $winner->id);
        $this->assertCount(1, $report);
        $this->assertSame(900.0, $report[0]['amount']);
        $this->assertSame(0, EarningTransaction::query()->count());
    }

    private function makeContest(array $overrides = []): Contest
    {
        return Contest::query()->create(array_merge([
            'slug' => 'prize-'.uniqid(),
            'title' => 'Prize Contest',
            'category' => 'admin',
            'contest_type' => 'group',
            'status' => 'active',
            'visibility' => 'public',
            'entry_fee' => 0,
            'prize_pool' => 5000,
            'voting_type' => 'community',
            'start_at' => now()->subDay(),
            'end_at' => now()->subHour(),
            'voting_end_at' => now()->subMinute(),
        ], $overrides));
    }

    private function submit(Contest $contest, User $user, float $community, float $final, int $rank): ContestSubmission
    {
        $post = Post::query()->create([
            'user_id' => $user->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'contest look',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'like_count' => 2,
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        return ContestSubmission::query()->create([
            'contest_id' => $contest->id,
            'user_id' => $user->id,
            'post_id' => $post->id,
            'submission_status' => 'approved',
            'community_score' => $community,
            'final_score' => $final,
            'rank_position' => $rank,
            'submitted_at' => now(),
        ]);
    }

    private function authenticatedUser(string $username): array
    {
        $user = User::factory()->create(['username' => $username, 'role' => 'user']);
        Profile::query()->create([
            'user_id' => $user->id,
            'display_name' => $username,
        ]);
        $token = str_repeat(substr(md5($username), 0, 1), 80);
        UserSession::query()->create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $token];
    }

    private function makeAdmin(string $username): User
    {
        $admin = User::factory()->create([
            'username' => $username,
            'role' => 'admin',
            'status' => 'active',
        ]);
        Profile::query()->create([
            'user_id' => $admin->id,
            'display_name' => $username,
        ]);

        return $admin;
    }

    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
