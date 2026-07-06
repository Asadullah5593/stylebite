<?php

namespace Tests\Feature;

use App\Models\EarningTransaction;
use App\Models\EarningsWallet;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSession;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_earnings_overview_returns_summary_breakdown_preview_and_recent_withdrawals(): void
    {
        [$user, $token] = $this->authenticatedUser();

        Profile::create([
            'user_id' => $user->id,
            'contest_wins' => 12,
        ]);

        $wallet = EarningsWallet::create([
            'user_id' => $user->id,
            'currency_code' => 'PKR',
            'available_balance' => 42850,
            'pending_balance' => 12000,
            'lifetime_earned' => 85000,
            'lifetime_withdrawn' => 30000,
        ]);

        EarningTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'transaction_type' => 'credit',
            'source_type' => 'contest_reward',
            'amount' => 15000,
            'currency_code' => 'PKR',
            'status' => 'completed',
            'note' => 'Contest reward',
            'metadata_json' => [
                'title' => 'Summer Streetwear',
                'reason' => 'Contest Win',
            ],
            'processed_at' => now()->subDays(2),
        ]);

        EarningTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'transaction_type' => 'credit',
            'source_type' => 'contest_reward',
            'amount' => 4500,
            'currency_code' => 'PKR',
            'status' => 'completed',
            'metadata_json' => [
                'title' => 'Minimalist Chic',
                'reason' => 'Top 5 Finish',
            ],
            'processed_at' => now()->subDays(3),
        ]);

        EarningTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'transaction_type' => 'credit',
            'source_type' => 'engagement_bonus',
            'amount' => 2000,
            'currency_code' => 'PKR',
            'status' => 'completed',
            'metadata_json' => [
                'title' => 'Creator Bonus',
                'reason' => 'Engagement Reward',
            ],
            'processed_at' => now()->subDays(4),
        ]);

        EarningTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'transaction_type' => 'credit',
            'source_type' => 'engagement_bonus',
            'amount' => 10000,
            'currency_code' => 'PKR',
            'status' => 'completed',
            'processed_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
        ]);

        EarningTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'transaction_type' => 'credit',
            'source_type' => 'engagement_bonus',
            'amount' => 8474.58,
            'currency_code' => 'PKR',
            'status' => 'completed',
            'processed_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(2),
        ]);

        WithdrawalRequest::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'amount' => 12000,
            'currency_code' => 'PKR',
            'method' => 'bank_transfer',
            'account_ref' => '****4201',
            'status' => 'processing',
            'requested_at' => now()->subDay(),
        ]);

        WithdrawalRequest::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'amount' => 25000,
            'currency_code' => 'PKR',
            'method' => 'bank_transfer',
            'account_ref' => '****4201',
            'status' => 'completed',
            'requested_at' => now()->subDays(3),
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/earnings/overview');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('summary.available_balance', 42850)
            ->assertJsonPath('summary.contest_wins', 12)
            ->assertJsonPath('summary.last_month_income_change_display', '+18%')
            ->assertJsonCount(3, 'earning_breakdown_preview')
            ->assertJsonCount(2, 'recent_withdrawals')
            ->assertJsonPath('earning_breakdown_preview.0.title', 'Summer Streetwear')
            ->assertJsonPath('recent_withdrawals.0.status', 'processing');
    }

    public function test_earnings_breakdowns_and_withdrawals_return_paginated_lists(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $wallet = EarningsWallet::create([
            'user_id' => $user->id,
            'currency_code' => 'PKR',
            'available_balance' => 1000,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            EarningTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_type' => 'credit',
                'source_type' => 'engagement_bonus',
                'amount' => 100 * $i,
                'currency_code' => 'PKR',
                'status' => 'completed',
                'processed_at' => now()->subDays($i),
            ]);
        }

        for ($i = 1; $i <= 4; $i++) {
            WithdrawalRequest::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'amount' => 50 * $i,
                'currency_code' => 'PKR',
                'method' => 'bank_transfer',
                'status' => 'pending',
                'requested_at' => now()->subDays($i),
            ]);
        }

        $this->withHeaders($this->headers($token))
            ->getJson('/api/earnings/breakdowns?page=1')
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 12)
            ->assertJsonCount(10, 'breakdowns');

        $this->withHeaders($this->headers($token))
            ->getJson('/api/earnings/withdrawals?page=1')
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 4)
            ->assertJsonCount(4, 'withdrawals');
    }

    public function test_user_can_submit_withdrawal_request(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $wallet = EarningsWallet::create([
            'user_id' => $user->id,
            'currency_code' => 'PKR',
            'available_balance' => 42850,
            'pending_balance' => 0,
            'lifetime_earned' => 50000,
            'lifetime_withdrawn' => 0,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->postJson('/api/earnings/withdrawals', [
                'amount' => 12000,
                'method' => 'bank_transfer',
                'account_ref' => '****4201',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('wallet.available_balance', 30850)
            ->assertJsonPath('wallet.pending_balance', 12000)
            ->assertJsonPath('withdrawal.status', 'pending')
            ->assertJsonPath('transaction.transaction_type', 'debit');

        $this->assertDatabaseHas('withdrawal_requests', [
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'amount' => 12000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('earning_transactions', [
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'transaction_type' => 'debit',
            'source_type' => 'withdrawal',
            'amount' => 12000,
            'status' => 'pending',
        ]);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create([
            'username' => 'earnings_user',
        ]);
        $token = str_repeat('e', 80);

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
