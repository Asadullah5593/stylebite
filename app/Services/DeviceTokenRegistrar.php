<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single write-path for FCM device tokens.
 *
 * Registration used to happen only inside login, which meant a token that FCM
 * rotated — app reinstall, cleared data, restore onto a new handset, or Firebase's
 * own periodic refresh — left a dead row in the database until the user happened
 * to log out and back in. Every notification to that device then failed with
 * INVALID_ARGUMENT while looking, from the panel, like a delivered campaign.
 *
 * Both login and the standalone refresh endpoint go through here so the two can
 * never drift apart.
 */
class DeviceTokenRegistrar
{
    /**
     * Create or move a device's push token, honouring both unique constraints on
     * the table: (user_id, device_id) and (platform, push_token).
     */
    public function register(User $user, string $deviceId, string $platform, string $pushToken, ?string $appVersion = null): DeviceToken
    {
        if (! in_array($platform, ['ios', 'android', 'web'], true)) {
            throw ValidationException::withMessages([
                'platform' => ['Push token storage only supports ios, android, or web platforms.'],
            ]);
        }

        return DB::transaction(function () use ($user, $deviceId, $platform, $pushToken, $appVersion): DeviceToken {
            $existingForDevice = DeviceToken::query()
                ->where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->first();

            // Matched across all users on purpose: a handset that now belongs to
            // somebody else has to be re-pointed, not duplicated.
            $existingForPushToken = DeviceToken::query()
                ->where('platform', $platform)
                ->where('push_token', $pushToken)
                ->first();

            if (
                $existingForDevice
                && $existingForPushToken
                && $existingForDevice->id !== $existingForPushToken->id
            ) {
                $existingForDevice->delete();
            }

            $deviceToken = $existingForPushToken ?? $existingForDevice ?? new DeviceToken;

            $deviceToken->forceFill([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'platform' => $platform,
                'push_token' => $pushToken,
                'app_version' => $appVersion,
                'is_active' => true,
                'last_used_at' => now(),
            ])->save();

            return $deviceToken;
        });
    }

    /**
     * Drop a token FCM has told us is dead.
     *
     * The row is deleted rather than deactivated because nothing reactivates one:
     * a dead token is not a disabled device, it is a string that will never
     * address anything again. The next login or refresh re-creates the row.
     */
    public function forget(DeviceToken $deviceToken): void
    {
        $deviceToken->delete();
    }
}
