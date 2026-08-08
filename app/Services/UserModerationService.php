<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DeviceToken;
use App\Models\ModerationAction;
use App\Models\User;
use App\Models\UserSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single write-path for account-level moderation. Every ban, suspension,
 * and reinstatement — from the user list, the report queue, or the bulk bar —
 * goes through here so the side effects can never drift apart: the status
 * change, the reasoned moderation_actions entry, the activity_logs audit row,
 * and the session revocation that makes the change bite immediately.
 */
class UserModerationService
{
    public function ban(User $user, User $moderator, string $reason): void
    {
        DB::transaction(function () use ($user, $moderator, $reason): void {
            $oldStatus = $user->status;

            $user->forceFill([
                'status' => 'banned',
                'suspended_until' => null,
                'status_reason' => $reason,
            ])->save();

            $this->revokeAccess($user);
            $this->recordAction($user, $moderator, 'ban', $reason, null);
            $this->logLifecycle($user, $moderator, 'ban', $oldStatus, 'banned', $reason);
        });
    }

    public function suspend(User $user, User $moderator, string $reason, ?CarbonInterface $until): void
    {
        DB::transaction(function () use ($user, $moderator, $reason, $until): void {
            $oldStatus = $user->status;

            $user->forceFill([
                'status' => 'suspended',
                'suspended_until' => $until,
                'status_reason' => $reason,
            ])->save();

            $this->revokeAccess($user);
            $this->recordAction($user, $moderator, 'suspend', $reason, $until);
            $this->logLifecycle($user, $moderator, 'suspend', $oldStatus, 'suspended', $reason, $until);
        });
    }

    /**
     * Admin "activate": lifts a ban or suspension (also used to activate a
     * never-verified account by hand). Logged as unban/unsuspend so the
     * moderation history reads correctly.
     */
    public function reinstate(User $user, User $moderator, ?string $reason = null): void
    {
        DB::transaction(function () use ($user, $moderator, $reason): void {
            $oldStatus = $user->status;

            $user->forceFill([
                'status' => 'active',
                'suspended_until' => null,
                'status_reason' => null,
            ])->save();

            $action = match ($oldStatus) {
                'banned' => 'unban',
                'suspended' => 'unsuspend',
                default => 'restore',
            };

            $this->recordAction($user, $moderator, $action, $reason, null);
            $this->logLifecycle($user, $moderator, 'activate', $oldStatus, 'active', $reason);
        });
    }

    /**
     * System path: a suspension window has passed, reactivate on the spot.
     * Runs lazily from the auth paths and nightly from the sweep command, so
     * it must stay cheap — one UPDATE plus one audit row, no moderator.
     */
    public function liftExpiredSuspension(User $user): void
    {
        $expiredAt = $user->suspended_until;

        $user->forceFill([
            'status' => 'active',
            'suspended_until' => null,
            'status_reason' => null,
        ])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'actor_type' => 'system',
            'event_name' => 'user_suspension_expired',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata_json' => [
                'suspended_until' => $expiredAt?->toDateTimeString(),
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * Apply one action to many users with per-user audit rows but O(1) queries:
     * one users UPDATE, one moderation_actions insert, one session revocation,
     * one device-token deactivation, one activity_logs summary row.
     *
     * @param  Collection<int, User>  $users  pre-filtered eligible users
     * @return int how many users were affected
     */
    public function bulk(Collection $users, User $moderator, string $action, ?string $reason, ?CarbonInterface $until = null): int
    {
        if ($users->isEmpty()) {
            return 0;
        }

        $now = now();
        $ids = $users->pluck('id')->all();

        DB::transaction(function () use ($users, $ids, $moderator, $action, $reason, $until, $now): void {
            User::query()->whereIn('id', $ids)->update(match ($action) {
                'ban' => ['status' => 'banned', 'suspended_until' => null, 'status_reason' => $reason],
                'suspend' => ['status' => 'suspended', 'suspended_until' => $until, 'status_reason' => $reason],
                default => ['status' => 'active', 'suspended_until' => null, 'status_reason' => null],
            });

            if (in_array($action, ['ban', 'suspend'], true)) {
                $this->revokeAccessBulk($ids, $now);
            }

            // $users was loaded before the UPDATE above, so $user->status here
            // is the pre-change status — exactly what unban/unsuspend needs.
            ModerationAction::insert($users->map(fn (User $user) => [
                'moderator_user_id' => $moderator->id,
                'target_type' => 'user',
                'target_id' => $user->id,
                'action' => match ($action) {
                    'ban' => 'ban',
                    'suspend' => 'suspend',
                    default => $user->status === 'banned' ? 'unban' : ($user->status === 'suspended' ? 'unsuspend' : 'restore'),
                },
                'reason' => $reason,
                'expires_at' => $action === 'suspend' ? $until?->toDateTimeString() : null,
                'created_at' => $now,
            ])->all());

            ActivityLog::create([
                'user_id' => $moderator->id,
                'actor_type' => 'admin',
                'event_name' => 'user_bulk_lifecycle_updated',
                'entity_type' => 'user',
                'entity_id' => null,
                'metadata_json' => [
                    'action' => $action,
                    'user_ids' => $ids,
                    'count' => count($ids),
                    'reason' => $reason,
                    'suspended_until' => $until?->toDateTimeString(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => $now,
            ]);
        });

        return count($ids);
    }

    /**
     * Kill every live token and push target so a ban/suspension takes effect
     * on the next request, not at the next login.
     */
    private function revokeAccess(User $user): void
    {
        $this->revokeAccessBulk([$user->id], now());
    }

    private function revokeAccessBulk(array $userIds, CarbonInterface $now): void
    {
        UserSession::query()
            ->whereIn('user_id', $userIds)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $now,
                'expires_at' => $now,
            ]);

        DeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function recordAction(User $user, User $moderator, string $action, ?string $reason, ?CarbonInterface $until): void
    {
        ModerationAction::create([
            'moderator_user_id' => $moderator->id,
            'target_type' => 'user',
            'target_id' => $user->id,
            'action' => $action,
            'reason' => $reason,
            'expires_at' => $until,
            'created_at' => now(),
        ]);
    }

    private function logLifecycle(User $user, User $moderator, string $action, string $oldStatus, string $newStatus, ?string $reason, ?CarbonInterface $until = null): void
    {
        ActivityLog::create([
            'user_id' => $moderator->id,
            'actor_type' => 'admin',
            'event_name' => 'user_lifecycle_updated',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata_json' => [
                'action' => $action,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'suspended_until' => $until?->toDateTimeString(),
                'email' => $user->email,
                'role' => $user->role,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
