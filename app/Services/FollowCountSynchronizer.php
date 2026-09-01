<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\UserFollow;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps profiles.follower_count / following_count honest.
 *
 * Those two columns are a cache of user_follows, and a cache is only as good as
 * the places that refresh it. They used to be refreshed on follow and unfollow
 * and nowhere else, so blocking someone — which removes the follow in both
 * directions — left the counts reading high, and so did deleting an account.
 * Everything that can change who follows whom now comes through here.
 *
 * A follow only counts when the person on the other end still exists. Users are
 * soft-deleted, so their rows in user_follows outlive them; counting those is
 * how a profile ends up reporting followers that no account matches.
 */
class FollowCountSynchronizer
{
    /** Recompute both counters for each of these users. */
    public function sync(int ...$userIds): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            Profile::query()
                ->firstOrCreate(['user_id' => $userId])
                ->update([
                    'follower_count' => $this->countFollowersOf($userId),
                    'following_count' => $this->countFollowedBy($userId),
                ]);
        }
    }

    /**
     * Recompute for a user AND everyone on the other end of their follows.
     * Deleting an account changes someone else's totals, not just their own —
     * every person they followed loses a follower, and every follower of theirs
     * loses a following.
     */
    public function syncWithCounterparts(int $userId): void
    {
        $counterparts = UserFollow::query()
            ->where('follower_user_id', $userId)
            ->pluck('following_user_id')
            ->merge(
                UserFollow::query()
                    ->where('following_user_id', $userId)
                    ->pluck('follower_user_id')
            )
            ->push($userId)
            ->unique()
            ->all();

        $this->sync(...array_map('intval', $counterparts));
    }

    /** People following this user, ignoring any whose account is gone. */
    private function countFollowersOf(int $userId): int
    {
        return $this->acceptedFollows()
            ->where('user_follows.following_user_id', $userId)
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'user_follows.follower_user_id')
                    ->whereNull('users.deleted_at');
            })
            ->count();
    }

    /** People this user follows, ignoring any whose account is gone. */
    private function countFollowedBy(int $userId): int
    {
        return $this->acceptedFollows()
            ->where('user_follows.follower_user_id', $userId)
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'user_follows.following_user_id')
                    ->whereNull('users.deleted_at');
            })
            ->count();
    }

    private function acceptedFollows(): Builder
    {
        return DB::table('user_follows')
            ->where('user_follows.status', 'accepted')
            ->whereNull('user_follows.deleted_at');
    }
}
