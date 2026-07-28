<?php

namespace App\Console\Commands;

use App\Models\AdImpression;
use App\Models\EarningsWallet;
use App\Models\EarningTransaction;
use App\Models\User;
use App\Services\CurrencyConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Credits reel owners their accumulated ad-revenue share.
 *
 * Per-impression amounts are micro-fractions, so we do NOT credit per
 * impression. This sums each owner's PENDING shares, converts once
 * (impression currency -> wallet currency, frozen), credits a single
 * ad_revenue transaction, and marks exactly those impressions settled.
 *
 * Money-safety: the whole run holds a single atomic lock (no two runs
 * overlap), and each owner's shares are read with lockForUpdate INSIDE the
 * transaction so the credit is driven by — and only settles — the exact rows
 * it locked. Impressions inserted mid-run keep status 'pending' for next time.
 */
class SettleAdEarningsCommand extends Command
{
    protected $signature = 'stylebite:settle-ad-earnings {--dry-run : Report what would be credited without writing}';

    protected $description = 'Convert and credit pending reel-owner ad-revenue shares into their wallets.';

    public function handle(CurrencyConverter $converter): int
    {
        // One settle run at a time — prevents overlapping cron runs from
        // double-crediting the same pending impressions.
        $lock = Cache::lock('stylebite:settle-ad-earnings', 900);

        if (! $lock->get()) {
            $this->warn('Another ad-earnings settlement is already running; skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->settle($converter);
        } finally {
            $lock->release();
        }
    }

    private function settle(CurrencyConverter $converter): int
    {
        $minPayout = max(0, (float) stylebite_app_config('ads.min_payout_threshold', 1));
        $dryRun = (bool) $this->option('dry-run');

        // Distinct owner + impression-currency groups that have pending shares.
        $groups = AdImpression::query()
            ->where('status', 'pending')
            ->whereNotNull('owner_user_id')
            ->where('owner_share', '>', 0)
            ->select('owner_user_id', 'currency_code')
            ->distinct()
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No pending ad earnings to settle.');

            return self::SUCCESS;
        }

        $credited = 0;
        $held = 0;

        foreach ($groups as $group) {
            $result = $dryRun
                ? $this->reportGroup($converter, $group, $minPayout)
                : $this->settleGroup($converter, $group, $minPayout);

            $result === 'credited' ? $credited++ : $held++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '')."Settled {$credited} owner(s); {$held} held/skipped.");

        return self::SUCCESS;
    }

    private function reportGroup(CurrencyConverter $converter, $group, float $minPayout): string
    {
        $baseAmount = round((float) AdImpression::query()
            ->where('status', 'pending')
            ->where('owner_user_id', $group->owner_user_id)
            ->where('currency_code', $group->currency_code)
            ->where('owner_share', '>', 0)
            ->sum('owner_share'), 2);

        if ($baseAmount < $minPayout) {
            $this->line("HOLD  user {$group->owner_user_id}: {$baseAmount} {$group->currency_code} (< {$minPayout})");

            return 'held';
        }

        $this->info("WOULD CREDIT user {$group->owner_user_id}: {$baseAmount} {$group->currency_code}");

        return 'credited';
    }

    private function settleGroup(CurrencyConverter $converter, $group, float $minPayout): string
    {
        return DB::transaction(function () use ($converter, $group, $minPayout) {
            // Lock exactly the pending rows for this owner+currency. A concurrent
            // run blocks here, then sees them 'settled' and finds nothing.
            $rows = AdImpression::query()
                ->where('status', 'pending')
                ->where('owner_user_id', $group->owner_user_id)
                ->where('currency_code', $group->currency_code)
                ->where('owner_share', '>', 0)
                ->lockForUpdate()
                ->get(['id', 'owner_share']);

            if ($rows->isEmpty()) {
                return 'held';
            }

            $baseAmount = round((float) $rows->sum('owner_share'), 2);

            if ($baseAmount < $minPayout) {
                return 'held'; // accumulate; leave pending
            }

            $user = User::query()->with('profile')->find($group->owner_user_id);

            if (! $user) {
                return 'held';
            }

            $wallet = $this->walletForUser($user);
            $conversion = $converter->convert($baseAmount, $group->currency_code, $wallet->currency_code);

            if ($conversion === null) {
                Log::warning('Ad earnings settle skipped: missing FX rate.', [
                    'user_id' => $user->id,
                    'from' => $group->currency_code,
                    'to' => $wallet->currency_code,
                ]);
                $this->warn("SKIP  user {$user->id}: no FX rate {$group->currency_code} -> {$wallet->currency_code}");

                return 'held';
            }

            $amount = round((float) $conversion['amount'], 2);

            $transaction = EarningTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_type' => 'credit',
                'source_type' => 'ad_revenue',
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'base_amount' => $baseAmount,
                'base_currency_code' => $group->currency_code,
                'fx_rate' => $conversion['rate'],
                'fx_rate_at' => $conversion['rate_at'],
                'status' => 'completed',
                'note' => 'Ad revenue payout',
                'metadata_json' => [
                    'reason' => 'Reel ad revenue share',
                    'impressions' => $rows->count(),
                ],
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $wallet->forceFill([
                'available_balance' => round((float) $wallet->available_balance + $amount, 2),
                'lifetime_earned' => round((float) $wallet->lifetime_earned + $amount, 2),
                'updated_balance_at' => now(),
            ])->save();

            // Settle ONLY the rows we locked and summed — not any inserted since.
            AdImpression::query()
                ->whereIn('id', $rows->pluck('id'))
                ->update([
                    'status' => 'settled',
                    'settled_transaction_id' => $transaction->id,
                    'updated_at' => now(),
                ]);

            $this->info("CREDITED user {$user->id}: {$baseAmount} {$group->currency_code} = {$amount} {$wallet->currency_code}");

            return 'credited';
        });
    }

    /**
     * Mirror EarningsController::walletForUser so ad payouts land in the same
     * wallet with the same country-based currency.
     */
    private function walletForUser(User $user): EarningsWallet
    {
        $currencyCode = stylebite_currency_for_country($user->profile?->country)
            ?? Str::upper((string) stylebite_app_config('earnings.default_currency_code', 'PKR'));

        return EarningsWallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'currency_code' => $currencyCode,
                'available_balance' => 0,
                'pending_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_withdrawn' => 0,
            ]
        );
    }
}
