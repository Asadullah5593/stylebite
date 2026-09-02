<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileBadge;

/**
 * Keeps profiles.is_verified_badge honest against the badge that actually grants it.
 *
 * The blue tick had two disconnected sources of truth. Admins grant and revoke it
 * by adding or removing a `verified_user` row in profile_badges, but the API reads
 * a separate profiles.is_verified_badge column — and nothing kept the two in step.
 * So an admin could award the badge, see it in the panel, and the app would still
 * show the user as unverified. The only thing that ever wrote the column was a
 * self-service endpoint, which is now gone.
 *
 * The column stays because it is read on every feed item, where the profile is
 * already loaded and a badge lookup would not be. It is a cache of one question:
 * does this user hold the verified_user badge? This class is the only answer to it,
 * and ProfileBadge's model events call it — so a future caller cannot forget to,
 * which is exactly how the follower counts drifted.
 */
class VerifiedBadgeSynchronizer
{
    public const BADGE_KEY = 'verified_user';

    public function sync(int ...$userIds): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            Profile::query()
                ->firstOrCreate(['user_id' => $userId])
                ->update(['is_verified_badge' => $this->hasBadge($userId)]);
        }
    }

    /** Recompute for every user, for backfills after the two sources had drifted. */
    public function syncAll(): int
    {
        $userIds = Profile::query()->pluck('user_id')
            ->merge(ProfileBadge::query()->where('badge_key', self::BADGE_KEY)->pluck('user_id'))
            ->unique();

        $this->sync(...$userIds->all());

        return $userIds->count();
    }

    private function hasBadge(int $userId): bool
    {
        return ProfileBadge::query()
            ->where('user_id', $userId)
            ->where('badge_key', self::BADGE_KEY)
            ->exists();
    }
}
