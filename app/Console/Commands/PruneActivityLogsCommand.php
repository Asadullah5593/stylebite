<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * Keeps activity_logs bounded. Every admin action now writes a row, so the
 * audit trail grows steadily — the retention window (config/audit.php, default
 * 365 days) decides how far back it reaches. Deletes in batches to avoid
 * holding a long lock.
 *
 * Meant for cron, e.g. weekly:
 *   php /path/to/artisan stylebite:prune-activity-logs
 */
class PruneActivityLogsCommand extends Command
{
    protected $signature = 'stylebite:prune-activity-logs {--days= : Override the retention window in days}';

    protected $description = 'Delete activity-log rows older than the retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('audit.retention_days', 365));

        if ($days < 30) {
            $this->error('Refusing to prune with a retention window under 30 days — an audit trail that short is not much of a trail.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $batch = ActivityLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit(5000)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} activity-log row(s) older than {$cutoff->toDateString()} ({$days} days).");

        return self::SUCCESS;
    }
}
