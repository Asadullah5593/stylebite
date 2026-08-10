<?php

namespace App\Console\Commands;

use App\Models\AutomatedNotificationSend;
use App\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Nudges users whose streak is about to lapse.
 *
 * "About to lapse" means: they have a live streak, their last qualifying day was
 * yesterday, and they have not qualified again today — so the nightly
 * refresh-streaks run will break it at midnight unless they act.
 *
 * Safe to run hourly: automated_notification_sends keys one reminder per user
 * per day, so a second run the same day is a no-op. Users only ever hear about
 * it once, on the day it matters.
 *
 * Cron, e.g. every hour during waking hours:
 *   php /path/to/artisan stylebite:send-streak-reminders
 */
class SendStreakRemindersCommand extends Command
{
    protected $signature = 'stylebite:send-streak-reminders {--limit=500 : Maximum reminders to send in one run}';

    protected $description = 'Notify users whose daily streak will break tonight unless they post.';

    public function handle(): int
    {
        if (! stylebite_app_config('streaks.reminder_enabled', true)) {
            $this->info('Streak reminders are disabled in Admin → Settings.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $timezone = stylebite_reporting_timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $yesterday = $today->subDay();
        $scopeKey = $today->toDateString();

        // At risk: a live streak whose last counted day is yesterday. If
        // last_streak_day were today they have already qualified, and if it were
        // older the streak is already gone.
        $atRisk = Profile::query()
            ->where('current_streak_days', '>', 0)
            ->whereDate('last_streak_day', $yesterday->toDateString())
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->whereDoesntHave('user.automatedNotificationSends', fn ($query) => $query
                ->where('kind', AutomatedNotificationSend::KIND_STREAK_REMINDER)
                ->where('scope_key', $scopeKey))
            ->with('user:id,username,full_name')
            ->limit($limit + 1)
            ->get(['id', 'user_id', 'current_streak_days']);

        $capped = $atRisk->count() > $limit;
        $atRisk = $atRisk->take($limit);

        if ($atRisk->isEmpty()) {
            $this->info('No streaks at risk right now.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($atRisk as $profile) {
            if (! $profile->user) {
                continue;
            }

            $days = (int) $profile->current_streak_days;

            // Claim the send first. If two overlapping runs race, the unique key
            // makes the loser fail here rather than double-notifying the user.
            $claimed = AutomatedNotificationSend::query()->insertOrIgnore([
                'user_id' => $profile->user_id,
                'kind' => AutomatedNotificationSend::KIND_STREAK_REMINDER,
                'scope_key' => $scopeKey,
                'created_at' => now(),
            ]);

            if ($claimed === 0) {
                continue;
            }

            stylebite_notify_user(
                (int) $profile->user_id,
                null,
                'system',
                'user',
                (int) $profile->user_id,
                "Your {$days}-day streak ends tonight",
                'Post today to keep your streak alive.',
                null,
                null
            );

            $sent++;
        }

        $this->info("Sent {$sent} streak reminder(s) for {$scopeKey}.");

        if ($capped) {
            // Never silently truncate: say so, so the operator knows another run
            // is needed to clear the backlog.
            $this->warn("Hit the {$limit} reminder cap — more users are still at risk today. Run again to continue.");
        }

        return self::SUCCESS;
    }
}
