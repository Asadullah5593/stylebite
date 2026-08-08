<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Reactivate accounts whose suspension window has passed. The auth paths
 * already lift expired suspensions lazily when the user comes back, so this
 * sweep exists for the users who don't: it keeps dashboard counts honest and
 * makes the "suspended" list mean what it says.
 *
 * Meant for cron, e.g. hourly:
 *   php /path/to/artisan stylebite:lift-expired-suspensions
 */
class LiftExpiredSuspensionsCommand extends Command
{
    protected $signature = 'stylebite:lift-expired-suspensions';

    protected $description = 'Reactivate users whose suspension window has ended.';

    public function handle(): int
    {
        $expired = User::query()
            ->where('status', 'suspended')
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->pluck('id');

        if ($expired->isEmpty()) {
            $this->info('No expired suspensions found.');

            return self::SUCCESS;
        }

        User::query()
            ->whereIn('id', $expired)
            ->update([
                'status' => 'active',
                'suspended_until' => null,
                'status_reason' => null,
            ]);

        ActivityLog::create([
            'user_id' => null,
            'actor_type' => 'system',
            'event_name' => 'user_suspensions_swept',
            'entity_type' => 'user',
            'entity_id' => null,
            'metadata_json' => [
                'user_ids' => $expired->all(),
                'count' => $expired->count(),
            ],
            'created_at' => now(),
        ]);

        $this->info("Lifted {$expired->count()} expired suspension(s).");

        return self::SUCCESS;
    }
}
