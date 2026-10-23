<?php

namespace App\Support;

use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\ContestPlace;
use App\Models\ContestSubmission;
use App\Models\EarningTransaction;
use App\Models\EarningsWallet;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContestResults
{
    public static function finish(Contest $contest): bool
    {
        $contest->status = 'completed';
        $contest->result_at = $contest->result_at ?? now();
        self::refreshScores($contest);
        self::assignOpenPlaces($contest);
        $contest->save();
        self::creditContest($contest);
        self::syncProfiles($contest);

        return ContestNotifications::announceWinners($contest->fresh());
    }

    public static function winCount(int $userId): int
    {
        $fromPlaces = ContestPlace::query()
            ->where('place', 1)
            ->where('winner_user_id', $userId)
            ->whereHas('contest', fn ($query) => $query->where('status', 'completed'))
            ->distinct()
            ->count('contest_id');

        $fromLegacy = Contest::query()
            ->where('status', 'completed')
            ->where('winner_user_id', $userId)
            ->whereDoesntHave('places', fn ($query) => $query->where('place', 1)->whereNotNull('winner_user_id'))
            ->count();

        return $fromPlaces + $fromLegacy;
    }

    public static function entryCount(int $userId): int
    {
        return (int) ContestSubmission::query()
            ->where('user_id', $userId)
            ->whereNotIn('submission_status', ['rejected', 'disqualified'])
            ->distinct()
            ->count('contest_id');
    }

    public static function resultsMissing(Contest $contest): bool
    {
        $places = $contest->places()->get(['id', 'winner_user_id']);

        if ($places->isEmpty()) {
            return $contest->winner_user_id === null;
        }

        return $places->every(fn (ContestPlace $place) => $place->winner_user_id === null);
    }

    public static function scoreFrom(float $averageRating, int $totalLikes): float
    {
        return round(($averageRating * 70) + ($totalLikes * 0.3), 2);
    }

    public static function applyScore(ContestSubmission $submission): void
    {
        $submission->loadMissing(['votes', 'post:id,like_count']);

        $average = $submission->votes->isEmpty()
            ? 0.0
            : (float) $submission->votes->avg('score');
        $likes = max(0, (int) ($submission->post->like_count ?? 0));

        $submission->community_score = round($average, 2);
        $submission->final_score = self::scoreFrom($average, $likes);
    }

    public static function refreshPost(int $postId): void
    {
        $contestIds = ContestSubmission::query()
            ->where('post_id', $postId)
            ->pluck('contest_id')
            ->unique();

        foreach ($contestIds as $contestId) {
            $contest = Contest::query()->find($contestId);
            if (! $contest) {
                continue;
            }

            self::refreshScores($contest);
            $contest->save();
        }
    }

    public static function refreshScores(Contest $contest): void
    {
        $submissions = self::rankedSubmissions($contest);

        foreach ($submissions as $index => $submission) {
            $submission->rank_position = $index + 1;
            $submission->save();
        }

        $bestByUser = [];
        foreach ($submissions as $submission) {
            $userId = (int) $submission->user_id;
            if ($userId > 0 && ! isset($bestByUser[$userId])) {
                $bestByUser[$userId] = (float) $submission->final_score;
            }
        }

        $position = 1;
        foreach ($bestByUser as $userId => $score) {
            ContestParticipant::query()
                ->where('contest_id', $contest->id)
                ->where('user_id', $userId)
                ->update([
                    'rank_position' => $position,
                    'total_score' => $score,
                ]);
            $position++;
        }

        $contest->total_vote_count = $submissions->sum(fn (ContestSubmission $submission) => $submission->votes->count());
    }

    public static function assignOpenPlaces(Contest $contest): void
    {
        $places = $contest->places()->orderBy('place')->get();
        $rankedUserIds = [];
        foreach (self::rankedSubmissions($contest) as $submission) {
            $userId = (int) $submission->user_id;
            if ($userId > 0 && ! in_array($userId, $rankedUserIds, true)) {
                $rankedUserIds[] = $userId;
            }
        }

        if ($places->isEmpty()) {
            $contest->winner_user_id = $contest->winner_user_id ?: ($rankedUserIds[0] ?? null);
            if ($contest->winner_user_id) {
                $contest->winner_team_id = null;
            }

            return;
        }

        $used = $places
            ->pluck('winner_user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($places as $place) {
            if ($place->winner_user_id) {
                continue;
            }

            foreach ($rankedUserIds as $userId) {
                if (in_array($userId, $used, true)) {
                    continue;
                }
                $place->winner_user_id = $userId;
                $place->save();
                $used[] = $userId;
                break;
            }
        }

        $firstWinner = $places->first(fn (ContestPlace $place) => $place->winner_user_id)?->winner_user_id;
        if ($firstWinner) {
            $contest->winner_user_id = $firstWinner;
            $contest->winner_team_id = null;
        }
    }

    /**
     * Credit every unfinished prize place for one user. Intended for an
     * approved reconciliation pass — not for normal My Earnings / profile reads.
     */
    public static function creditCompletedPrizes(int $userId): void
    {
        $places = ContestPlace::query()
            ->where('winner_user_id', $userId)
            ->where('prize_amount', '>', 0)
            ->whereHas('contest', fn ($query) => $query->where('status', 'completed'))
            ->with('contest:id,title,status')
            ->get();

        foreach ($places as $place) {
            self::creditPlace($place);
        }
    }

    public static function creditContest(Contest $contest): void
    {
        if ($contest->status !== 'completed') {
            return;
        }

        $places = $contest->places()->get();
        foreach ($places as $place) {
            $place->setRelation('contest', $contest);
            self::creditPlace($place);
        }
    }

    /**
     * Report completed prize places that still have no contest_reward ledger row.
     * Does not write wallet balances — use for staging / approved backfill review.
     *
     * @return list<array{contest_id:int,contest_title:string,place_id:int,place:int,user_id:int,amount:float}>
     */
    public static function missingPrizeCredits(?int $userId = null): array
    {
        $query = ContestPlace::query()
            ->where('prize_amount', '>', 0)
            ->whereNotNull('winner_user_id')
            ->whereHas('contest', fn ($q) => $q->where('status', 'completed'))
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('earning_transactions')
                    ->whereColumn('earning_transactions.source_id', 'contest_places.id')
                    ->where('earning_transactions.source_type', 'contest_reward');
            })
            ->with('contest:id,title,status');

        if ($userId !== null) {
            $query->where('winner_user_id', $userId);
        }

        return $query->get()->map(fn (ContestPlace $place) => [
            'contest_id' => (int) $place->contest_id,
            'contest_title' => (string) ($place->contest?->title ?? ''),
            'place_id' => (int) $place->id,
            'place' => (int) $place->place,
            'user_id' => (int) $place->winner_user_id,
            'amount' => round((float) $place->prize_amount, 2),
        ])->values()->all();
    }

    private static function creditPlace(ContestPlace $place): void
    {
        $amount = round((float) $place->prize_amount, 2);
        $userId = (int) $place->winner_user_id;
        if ($amount <= 0 || $userId <= 0) {
            return;
        }

        $contest = $place->relationLoaded('contest')
            ? $place->contest
            : Contest::query()->find($place->contest_id);
        if (! $contest || $contest->status !== 'completed') {
            return;
        }

        if (EarningTransaction::query()
            ->where('source_type', 'contest_reward')
            ->where('source_id', $place->id)
            ->exists()) {
            return;
        }

        $user = User::query()->with('profile')->find($userId);
        if (! $user) {
            return;
        }

        $currencyCode = stylebite_currency_for_country($user->profile?->country)
            ?? strtoupper((string) stylebite_app_config('earnings.default_currency_code', 'PKR'));

        $wallet = EarningsWallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'currency_code' => $currencyCode,
                'available_balance' => 0,
                'pending_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_withdrawn' => 0,
            ]
        );

        try {
            DB::transaction(function () use ($place, $contest, $user, $amount, $wallet): void {
                $wallet = EarningsWallet::query()->whereKey($wallet->id)->lockForUpdate()->first();
                $alreadyPaid = EarningTransaction::query()
                    ->where('source_type', 'contest_reward')
                    ->where('source_id', $place->id)
                    ->lockForUpdate()
                    ->exists();
                if ($alreadyPaid || ! $wallet) {
                    return;
                }

                // Pending only — available_balance and lifetime_earned move on admin approval.
                $wallet->pending_balance = round((float) $wallet->pending_balance + $amount, 2);
                $wallet->updated_balance_at = now();
                $wallet->save();

                $placeLabel = ContestPlace::ordinal((int) $place->place).' place';
                EarningTransaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'transaction_type' => 'credit',
                    'source_type' => 'contest_reward',
                    'source_id' => $place->id,
                    'amount' => $amount,
                    'currency_code' => $wallet->currency_code,
                    'status' => 'pending',
                    'note' => $placeLabel.' in '.$contest->title,
                    'metadata_json' => [
                        'title' => $placeLabel.' · '.$contest->title,
                        'reason' => 'Contest Win',
                        'contest_id' => $contest->id,
                        'place' => (int) $place->place,
                        'prize_status' => 'pending_approval',
                    ],
                    'processed_at' => null,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Concurrent finalization lost the race — unique(source_type, source_id) already holds.
        }
    }

    /**
     * Move one pending contest_reward from pending_balance into available_balance.
     * Returns the updated transaction, or null when nothing was eligible.
     */
    public static function approvePendingPrize(int $transactionId, int $adminUserId): ?EarningTransaction
    {
        return DB::transaction(function () use ($transactionId, $adminUserId) {
            $transaction = EarningTransaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->first();

            if (
                ! $transaction
                || $transaction->source_type !== 'contest_reward'
                || $transaction->transaction_type !== 'credit'
                || $transaction->status !== 'pending'
            ) {
                return null;
            }

            $wallet = EarningsWallet::query()
                ->whereKey($transaction->wallet_id)
                ->lockForUpdate()
                ->first();
            if (! $wallet) {
                return null;
            }

            $amount = round((float) $transaction->amount, 2);
            if ($amount <= 0) {
                return null;
            }

            if ((float) $wallet->pending_balance + 0.00001 < $amount) {
                throw new \RuntimeException('Wallet pending balance is insufficient to approve this contest prize.');
            }

            $wallet->pending_balance = round((float) $wallet->pending_balance - $amount, 2);
            $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
            $wallet->lifetime_earned = round((float) $wallet->lifetime_earned + $amount, 2);
            $wallet->updated_balance_at = now();
            $wallet->save();

            $metadata = is_array($transaction->metadata_json) ? $transaction->metadata_json : [];
            $metadata['prize_status'] = 'approved';
            $metadata['approved_by_admin_id'] = $adminUserId;
            $metadata['approved_at'] = now()->toDateTimeString();

            $transaction->status = 'completed';
            $transaction->metadata_json = $metadata;
            $transaction->processed_at = now();
            $transaction->save();

            return $transaction->fresh();
        });
    }

    private static function syncProfiles(Contest $contest): void
    {
        $userIds = ContestParticipant::query()
            ->where('contest_id', $contest->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->push((int) $contest->winner_user_id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            Profile::query()
                ->where('user_id', $userId)
                ->update([
                    'contest_wins' => self::winCount($userId),
                    'contest_entries' => self::entryCount($userId),
                ]);
        }
    }

    private static function rankedSubmissions(Contest $contest)
    {
        return ContestSubmission::query()
            ->with(['votes', 'post:id,like_count'])
            ->where('contest_id', $contest->id)
            ->whereNotIn('submission_status', ['rejected', 'disqualified'])
            ->whereHas('post', function ($query): void {
                $query->where('status', 'published')
                    ->where('is_blocked', false)
                    ->where(function ($moderation): void {
                        $moderation->whereNull('moderation_status')
                            ->orWhere('moderation_status', 'clean');
                    });
            })
            ->get()
            ->map(function (ContestSubmission $submission) {
                self::applyScore($submission);

                return $submission;
            })
            ->sortBy([
                ['final_score', 'desc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}
