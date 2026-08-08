<?php

namespace App\Console\Commands;

use App\Models\UserSession;
use Illuminate\Console\Command;

/**
 * Keeps user_sessions bounded. With the 24-hour session lifetime every user
 * writes at least one row per day, so dead sessions pile up fast. Rows are
 * kept for a grace window after they die so the admin panel's session history
 * stays useful, then deleted in batches to avoid holding a long lock.
 *
 * Meant for cron, e.g. nightly:
 *   php /path/to/artisan stylebite:prune-user-sessions
 */
class PruneUserSessionsCommand extends Command
{
    protected $signature = 'stylebite:prune-user-sessions {--days=30 : Days to keep expired/revoked sessions}';

    protected $description = 'Delete sessions that expired or were revoked more than the retention window ago.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = 0;

        do {
            $batch = UserSession::query()
                ->where(function ($query) use ($cutoff) {
                    $query->where('expires_at', '<', $cutoff)
                        ->orWhere('revoked_at', '<', $cutoff);
                })
                ->limit(5000)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} dead session(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
