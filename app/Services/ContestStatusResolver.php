<?php

namespace App\Services;

use App\Models\Contest;

/**
 * Contest status used to be whatever an admin last set by hand. Nothing advanced
 * it, so a contest whose start_at had passed stayed "upcoming" forever and one
 * past its end_at stayed "active" until somebody happened to open it.
 *
 * Status is really a function of the clock, so it is derived here. The scheduled
 * command writes the answer back to the column — which listings and admin filters
 * query — while the API asks this class directly, so a response is never stale
 * between cron runs.
 */
class ContestStatusResolver
{
    /** The only three statuses the mobile app ever sees. */
    public const PUBLIC_STATUSES = ['upcoming', 'active', 'completed'];

    /** Admin-only states that never appear in the app and are never auto-advanced. */
    public const ADMIN_ONLY_STATUSES = ['draft', 'cancelled', 'archived'];

    public function forContest(Contest $contest): string
    {
        return $this->forDates($contest->start_at, $contest->end_at);
    }

    public function forDates(?\DateTimeInterface $startAt, ?\DateTimeInterface $endAt): string
    {
        $now = now();

        if ($endAt !== null && $now->greaterThanOrEqualTo($endAt)) {
            return 'completed';
        }

        // No start date means it has not been scheduled, so it has not started.
        if ($startAt !== null && $now->greaterThanOrEqualTo($startAt)) {
            return 'active';
        }

        return 'upcoming';
    }

    /**
     * The status to show for a contest. Admin-only states are reported as they
     * are so the panel stays honest; everything else follows the clock.
     */
    public function displayStatus(Contest $contest): string
    {
        if (in_array($contest->status, self::ADMIN_ONLY_STATUSES, true)) {
            return $contest->status;
        }

        return $this->forContest($contest);
    }

    /**
     * Advance stored statuses to match the clock. Returns how many rows moved.
     * Admin-only states are deliberately left alone — a cancelled contest should
     * not spring back to life because its dates say so.
     */
    public function refreshAll(): array
    {
        $now = now();

        $activated = Contest::query()
            ->where('status', 'upcoming')
            ->whereNotNull('start_at')
            ->where('start_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>', $now))
            ->update(['status' => 'active']);

        $completed = Contest::query()
            ->whereIn('status', ['upcoming', 'active'])
            ->whereNotNull('end_at')
            ->where('end_at', '<=', $now)
            ->update(['status' => 'completed']);

        return ['activated' => $activated, 'completed' => $completed];
    }
}
