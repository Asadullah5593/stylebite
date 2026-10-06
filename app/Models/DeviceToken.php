<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DeviceToken extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushNotificationLogs(): HasMany
    {
        return $this->hasMany(PushNotificationLog::class);
    }

    public static function syncForUser(
        User $user,
        string $deviceId,
        string $platform,
        string $pushToken,
        ?string $appVersion = null,
    ): self {
        $platform = $platform === 'desktop' ? 'web' : $platform;

        return DB::transaction(function () use ($user, $deviceId, $platform, $pushToken, $appVersion): self {
            $existingForDevice = static::query()
                ->where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->first();

            $existingForPushToken = static::query()
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

            $deviceToken = $existingForPushToken ?? $existingForDevice ?? new static;

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
}
