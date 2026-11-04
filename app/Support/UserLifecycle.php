<?php

namespace App\Support;

use App\Models\ContestSubmission;
use App\Models\ContestVote;
use App\Models\Post;
use App\Models\PostRating;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\DB;

/**
 * Side effects when admin changes a user's lifecycle status.
 *
 * Banned / suspended accounts cannot use the API (middleware). Their posts
 * stay in the DB but are filtered from feeds until status is active again.
 * Contest ratings cast BY a banned user are removed permanently and scores
 * are recalculated.
 */
final class UserLifecycle
{
    public static function afterStatusChange(User $user, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        if (in_array($newStatus, ['banned', 'inactive'], true)) {
            self::revokeSessions($user);
        }

        if ($newStatus === 'banned') {
            self::purgeContestRatingsCastBy($user);
        }
    }

    public static function revokeSessions(User $user): void
    {
        UserSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);
    }

    /**
     * Delete contest votes (and mirrored post ratings) cast by this user,
     * then refresh contest / post aggregates so leaderboards stay correct.
     */
    public static function purgeContestRatingsCastBy(User $user): void
    {
        $voterId = (int) $user->id;

        $votes = ContestVote::query()
            ->where('voter_user_id', $voterId)
            ->get(['id', 'contest_id', 'submission_id']);

        if ($votes->isEmpty()) {
            // Still clear any mirrored post ratings left from older paths.
            self::recalculatePostsAfterRatingPurge(
                PostRating::query()->where('user_id', $voterId)->pluck('post_id')->all()
            );
            PostRating::query()->where('user_id', $voterId)->delete();

            return;
        }

        $contestIds = $votes->pluck('contest_id')->unique()->filter()->values();
        $submissionIds = $votes->pluck('submission_id')->unique()->filter()->values();

        $postIds = ContestSubmission::query()
            ->whereIn('id', $submissionIds)
            ->pluck('post_id')
            ->merge(
                PostRating::query()->where('user_id', $voterId)->pluck('post_id')
            )
            ->unique()
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($voterId, $votes): void {
            ContestVote::query()->whereIn('id', $votes->pluck('id'))->delete();
            PostRating::query()->where('user_id', $voterId)->delete();
        });

        foreach ($contestIds as $contestId) {
            $contest = \App\Models\Contest::query()->find($contestId);
            if ($contest) {
                ContestResults::refreshScores($contest);
                $contest->save();
            }
        }

        self::recalculatePostsAfterRatingPurge($postIds);
    }

    /**
     * @param  list<int|string>  $postIds
     */
    private static function recalculatePostsAfterRatingPurge(array $postIds): void
    {
        foreach (array_unique(array_map('intval', $postIds)) as $postId) {
            if ($postId <= 0) {
                continue;
            }

            $average = PostRating::query()->where('post_id', $postId)->avg('rating_value');
            $count = PostRating::query()->where('post_id', $postId)->count();

            Post::query()->whereKey($postId)->update([
                'rating_avg' => $average !== null ? round((float) $average, 2) : 0,
                'rating_count' => $count,
            ]);
        }
    }
}
