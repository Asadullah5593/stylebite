<?php

namespace App\Support;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppVersionGate
{
    public static function isForceEnabled(): bool
    {
        return (bool) stylebite_app_config('app.force_update', false);
    }

    public static function minVersionForPlatform(string $platform): string
    {
        $key = $platform === 'ios'
            ? 'app.min_ios_version'
            : 'app.min_android_version';

        return self::normalizeVersion((string) stylebite_app_config($key, '0.0.0'));
    }

    public static function storeUrlForPlatform(string $platform): string
    {
        $key = $platform === 'ios'
            ? 'app.ios_store_url'
            : 'app.android_store_url';

        $storeUrl = trim((string) stylebite_app_config(
            $key,
            $platform === 'ios'
                ? config('services.stylebite.app_store_url', '')
                : config('services.stylebite.play_store_url', '')
        ));

        if ($storeUrl === '' || $storeUrl === '#') {
            return $platform === 'ios'
                ? 'https://apps.apple.com'
                : 'https://play.google.com/store/apps/details?id=com.stylebite.app';
        }

        return $storeUrl;
    }

    public static function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '') {
            return '0.0.0';
        }

        $version = preg_replace('/[+()].*$/', '', $version) ?? $version;
        $version = trim($version);
        $parts = array_map(
            static fn ($part) => (string) max(0, (int) $part),
            array_slice(explode('.', $version), 0, 3)
        );

        while (count($parts) < 3) {
            $parts[] = '0';
        }

        return implode('.', $parts);
    }

    /** @return int negative if $left < $right, 0 if equal, positive if $left > $right */
    public static function compareVersions(string $left, string $right): int
    {
        $leftParts = array_map('intval', explode('.', $left));
        $rightParts = array_map('intval', explode('.', $right));

        for ($i = 0; $i < 3; $i++) {
            $diff = ($leftParts[$i] ?? 0) <=> ($rightParts[$i] ?? 0);
            if ($diff !== 0) {
                return $diff;
            }
        }

        return 0;
    }

    public static function isBelowMinimum(string $clientVersion, string $platform): bool
    {
        if (! self::isForceEnabled()) {
            return false;
        }

        $minVersion = self::minVersionForPlatform($platform);
        if ($minVersion === '0.0.0') {
            return false;
        }

        $client = self::normalizeVersion($clientVersion);

        return self::compareVersions($client, $minVersion) < 0;
    }

    /**
     * Resolve platform + version from headers/body first. Falls back to the
     * user's latest device token only when the request itself has no version
     * (older builds). Preferring the request body avoids locking out a freshly
     * updated install whose device_tokens row is still stale.
     *
     * @return array{platform: string, version: string}|null
     */
    public static function resolveClient(Request $request, ?User $user = null): ?array
    {
        $platform = strtolower(trim((string) (
            $request->header('X-App-Platform')
            ?? $request->input('platform')
            ?? ''
        )));
        if ($platform === 'desktop') {
            $platform = 'web';
        }

        $version = trim((string) (
            $request->header('X-App-Version')
            ?? $request->input('app_version')
            ?? ''
        ));

        if (($platform === '' || $version === '') && $user !== null) {
            $device = DeviceToken::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->orderByDesc('last_used_at')
                ->orderByDesc('id')
                ->first();

            if ($device !== null) {
                if ($platform === '') {
                    $platform = strtolower((string) $device->platform);
                }
                if ($version === '' && filled($device->app_version)) {
                    $version = (string) $device->app_version;
                }
            }
        }

        if (! in_array($platform, ['ios', 'android'], true) || $version === '') {
            return null;
        }

        return [
            'platform' => $platform,
            'version' => self::normalizeVersion($version),
        ];
    }

    public static function blockedResponse(string $platform): JsonResponse
    {
        $minVersion = self::minVersionForPlatform($platform);

        return response()->json([
            'status_code' => 0,
            'code' => 'force_update',
            'message' => 'A required app update is available. Please update StyleBite from the store to continue.',
            'force_update' => true,
            'update_required' => true,
            'min_version' => $minVersion,
            'store_url' => self::storeUrlForPlatform($platform),
            'title' => 'Update required',
            'body' => 'A new version of StyleBite is available. Please update to continue.',
        ], 426);
    }

    /**
     * When force-update is on and this client is below minimum, return the
     * 426 payload. Otherwise null.
     */
    public static function rejectIfRequired(Request $request, ?User $user = null): ?JsonResponse
    {
        if (! self::isForceEnabled()) {
            return null;
        }

        $client = self::resolveClient($request, $user);
        if ($client === null) {
            return null;
        }

        if (! self::isBelowMinimum($client['version'], $client['platform'])) {
            return null;
        }

        return self::blockedResponse($client['platform']);
    }
}
