<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds user_daily_activity from history that already exists in other tables,
 * so the dashboard's DAU/MAU cards are not empty on the day tracking goes live.
 *
 * Only approximate: it reconstructs "this user did something that day" from the
 * traces users leave behind (posts, comments, likes, sessions). Live tracking in
 * SessionTokenAuth is exact and takes over from the moment it is deployed.
 *
 * One-time maintenance command — run it once after migrating, ideally off-peak,
 * since each source is a single INSERT ... SELECT over a date range.
 */
class BackfillUserActivityCommand extends Command
{
    protected $signature = 'stylebite:backfill-user-activity {--days=90 : How far back to reconstruct}';

    protected $description = 'Reconstruct historical daily-activity rows for DAU/MAU reporting.';

    /**
     * [table, user column, timestamp column] — each contributes the days its
     * rows prove the user was active.
     */
    private const SOURCES = [
        ['activity_logs', 'user_id', 'created_at'],
        ['posts', 'user_id', 'created_at'],
        ['comments', 'user_id', 'created_at'],
        ['post_likes', 'user_id', 'created_at'],
        ['user_sessions', 'user_id', 'created_at'],
        ['user_sessions', 'user_id', 'last_seen_at'],
        ['users', 'id', 'last_seen_at'],
    ];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $timezone = stylebite_reporting_timezone();
        $now = CarbonImmutable::now($timezone);

        // Rows are stored in UTC but activity_date is a local calendar day, so
        // shift each timestamp by the reporting offset before taking DATE().
        $offsetMinutes = (int) round($now->getOffset() / 60);
        $since = $now->subDays($days - 1)->startOfDay()->setTimezone('UTC')->toDateTimeString();
        $stamp = $now->setTimezone('UTC')->toDateTimeString();

        $before = DB::table('user_daily_activity')->count();

        foreach (self::SOURCES as [$table, $userColumn, $dateColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $dateColumn)) {
                $this->warn("Skipping {$table}.{$dateColumn} — not present in this database.");

                continue;
            }

            // INSERT IGNORE skips both the unique-key collisions (a day already
            // recorded by another source) and rows pointing at deleted users.
            DB::insert(
                "INSERT IGNORE INTO `user_daily_activity` (`user_id`, `activity_date`, `created_at`)
                 SELECT DISTINCT `{$userColumn}`, DATE(`{$dateColumn}` + INTERVAL ? MINUTE), ?
                 FROM `{$table}`
                 WHERE `{$dateColumn}` >= ? AND `{$userColumn}` IS NOT NULL",
                [$offsetMinutes, $stamp, $since]
            );

            $this->line("Backfilled from {$table}.{$dateColumn}");
        }

        $added = DB::table('user_daily_activity')->count() - $before;

        $this->info("Added {$added} activity day(s) across the last {$days} day(s) ({$timezone}).");

        return self::SUCCESS;
    }
}
