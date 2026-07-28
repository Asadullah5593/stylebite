<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Refreshes the cached profiles.ad_eligible flag for every creator who owns a
 * published video (a potential reel earner). The reels feed's `show_ad` and the
 * impressions revenue split both read this flag, so this keeps them current
 * without recomputing the expensive watch-hours aggregate per request.
 * Runs from cron; the eligibility endpoint also refreshes a creator's own flag.
 */
class RefreshAdEligibilityCommand extends Command
{
    protected $signature = 'stylebite:refresh-ad-eligibility';

    protected $description = 'Recompute and cache ad eligibility for reel-owning creators.';

    public function handle(): int
    {
        $ownerIds = Post::query()
            ->where('media_kind', 'video')
            ->where('status', 'published')
            ->distinct()
            ->pluck('user_id');

        if ($ownerIds->isEmpty()) {
            $this->info('No reel-owning creators to refresh.');

            return self::SUCCESS;
        }

        $eligible = 0;

        foreach ($ownerIds as $userId) {
            if (stylebite_refresh_ad_eligibility((int) $userId)['eligible']) {
                $eligible++;
            }
        }

        $this->info("Refreshed {$ownerIds->count()} creator(s); {$eligible} currently ad-eligible.");

        return self::SUCCESS;
    }
}
