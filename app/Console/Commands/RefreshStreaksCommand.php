<?php

namespace App\Console\Commands;

use App\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Breaks streaks that lapsed.
 *
 * Posting and logging in recompute a streak on the spot, but nothing fires when
 * a user simply stops — so a streak would otherwise sit at its old value
 * forever. This sweeps the users whose streak could have changed overnight.
 *
 * Deliberately narrow: a user with no streak and no recent activity cannot
 * start one by doing nothing, so they are skipped. That keeps the nightly cost
 * proportional to the number of engaged users, not to the whole user base.
 *
 * Meant for cron, nightly:
 *   php /path/to/artisan stylebite:refresh-streaks
 */
class RefreshStreaksCommand extends Command
{
    protected $signature = 'stylebite:refresh-streaks {--all : Recompute every profile, not just the ones at risk}';

    protected $description = 'Recompute daily streaks and break the ones that lapsed.';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now(stylebite_reporting_timezone())->subDays(2)->toDateString();

        $query = Profile::query()->select(['id', 'user_id', 'current_streak_days']);

        if (! $this->option('all')) {
            $query->where(fn ($builder) => $builder
                ->where('current_streak_days', '>', 0)
                ->orWhere('last_streak_day', '>=', $cutoff));
        }

        $checked = 0;
        $broken = 0;

        $query->chunkById(500, function ($profiles) use (&$checked, &$broken) {
            foreach ($profiles as $profile) {
                $before = (int) $profile->current_streak_days;
                $after = stylebite_recalculate_streak((int) $profile->user_id)['days'];

                $checked++;

                if ($before > 0 && $after === 0) {
                    $broken++;
                }
            }
        });

        $this->info("Checked {$checked} profile(s); {$broken} streak(s) broken.");

        return self::SUCCESS;
    }
}
