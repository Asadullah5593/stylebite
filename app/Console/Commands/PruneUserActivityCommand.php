<?php

namespace App\Console\Commands;

use App\Models\UserDailyActivity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Keeps user_daily_activity bounded. The table grows by roughly one row per
 * active user per day, so at scale it would otherwise never stop growing —
 * MAU only ever looks back 30 days, so anything past the retention window is
 * dead weight. Deletes in batches to avoid holding a long lock.
 *
 * Meant for cron, e.g. nightly:
 *   php /path/to/artisan stylebite:prune-user-activity
 */
class PruneUserActivityCommand extends Command
{
    protected $signature = 'stylebite:prune-user-activity {--days=90 : Retention window in days}';

    protected $description = 'Delete daily-activity rows older than the retention window.';

    public function handle(): int
    {
        $days = max(31, (int) $this->option('days'));
        $timezone = stylebite_reporting_timezone();
        $cutoff = CarbonImmutable::now($timezone)->subDays($days)->toDateString();

        $deleted = 0;

        do {
            $batch = UserDailyActivity::query()
                ->where('activity_date', '<', $cutoff)
                ->limit(5000)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} activity day(s) older than {$cutoff}.");

        return self::SUCCESS;
    }
}
