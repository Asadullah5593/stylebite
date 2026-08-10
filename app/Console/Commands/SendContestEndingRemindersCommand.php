<?php

namespace App\Console\Commands;

use App\Models\AutomatedNotificationSend;
use App\Models\Contest;
use App\Models\ContestParticipant;
use Illuminate\Console\Command;

/**
 * Warns contest participants that entries are about to close.
 *
 * Scope is deliberately narrow: active contests ending inside the configured
 * window, and only participants who are actually in the running (joined or
 * approved). Withdrawn, rejected and banned participants are left alone.
 *
 * Safe to run hourly: automated_notification_sends keys one reminder per user
 * per contest, so repeated runs inside the window do not re-notify anyone.
 *
 * Cron, e.g. hourly:
 *   php /path/to/artisan stylebite:send-contest-ending-reminders
 */
class SendContestEndingRemindersCommand extends Command
{
    protected $signature = 'stylebite:send-contest-ending-reminders {--limit=500 : Maximum reminders to send in one run}';

    protected $description = 'Notify contest participants that entries close soon.';

    public function handle(): int
    {
        if (! stylebite_app_config('contests.ending_soon_enabled', true)) {
            $this->info('Contest ending reminders are disabled in Admin → Settings.');

            return self::SUCCESS;
        }

        $hours = max(1, (int) stylebite_app_config('contests.ending_soon_hours', 24));
        $limit = max(1, (int) $this->option('limit'));

        $contests = Contest::query()
            ->where('status', 'active')
            ->whereNotNull('end_at')
            ->where('end_at', '>', now())
            ->where('end_at', '<=', now()->addHours($hours))
            ->get(['id', 'title', 'end_at']);

        if ($contests->isEmpty()) {
            $this->info("No active contests ending within {$hours} hour(s).");

            return self::SUCCESS;
        }

        $sent = 0;
        $remaining = $limit;

        foreach ($contests as $contest) {
            if ($remaining < 1) {
                break;
            }

            $participants = ContestParticipant::query()
                ->where('contest_id', $contest->id)
                ->whereIn('status', ['joined', 'approved'])
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->whereDoesntHave('user.automatedNotificationSends', fn ($query) => $query
                    ->where('kind', AutomatedNotificationSend::KIND_CONTEST_ENDING_SOON)
                    ->where('scope_key', (string) $contest->id))
                ->limit($remaining)
                ->get(['id', 'user_id']);

            foreach ($participants as $participant) {
                $claimed = AutomatedNotificationSend::query()->insertOrIgnore([
                    'user_id' => $participant->user_id,
                    'kind' => AutomatedNotificationSend::KIND_CONTEST_ENDING_SOON,
                    'scope_key' => (string) $contest->id,
                    'created_at' => now(),
                ]);

                if ($claimed === 0) {
                    continue;
                }

                stylebite_notify_user(
                    (int) $participant->user_id,
                    null,
                    'contest',
                    'contest',
                    (int) $contest->id,
                    $contest->title.' closes soon',
                    'Entries close '.$contest->end_at->diffForHumans().'. Finish your entry now.',
                    null,
                    null
                );

                $sent++;
                $remaining--;
            }
        }

        $this->info("Sent {$sent} contest reminder(s) across {$contests->count()} contest(s) ending within {$hours}h.");

        if ($remaining < 1) {
            $this->warn("Hit the {$limit} reminder cap — some participants have not been notified yet. Run again to continue.");
        }

        return self::SUCCESS;
    }
}
