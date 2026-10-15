<?php

namespace App\Support;

use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\ContestPlace;
use App\Models\ContestSubmission;
use App\Models\Profile;

class ContestResults
{
    public static function finish(Contest $contest): bool
    {
        $contest->status = 'completed';
        $contest->result_at = $contest->result_at ?? now();
        self::refreshScores($contest);
        self::assignOpenPlaces($contest);
        $contest->save();
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
            ->with('votes')
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
                $community = $submission->votes->isEmpty()
                    ? 0.0
                    : round((float) $submission->votes->avg('score'), 2);
                $submission->community_score = $community;
                $submission->final_score = round((float) ($submission->jury_score ?? 0) + $community, 2);

                return $submission;
            })
            ->sortBy([
                ['final_score', 'desc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}
