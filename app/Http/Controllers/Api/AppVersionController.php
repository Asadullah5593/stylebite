<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:ios,android'],
            'app_version' => ['required', 'string', 'max:32'],
        ]);

        $platform = $validated['platform'];
        $clientVersion = $this->normalizeVersion($validated['app_version']);
        $minKey = $platform === 'ios'
            ? 'app.min_ios_version'
            : 'app.min_android_version';
        $storeKey = $platform === 'ios'
            ? 'app.ios_store_url'
            : 'app.android_store_url';

        $minVersion = $this->normalizeVersion(
            (string) stylebite_app_config($minKey, '0.0.0')
        );
        $forceUpdate = (bool) stylebite_app_config('app.force_update', false);
        $storeUrl = trim((string) stylebite_app_config(
            $storeKey,
            $platform === 'ios'
                ? config('services.stylebite.app_store_url', '')
                : config('services.stylebite.play_store_url', '')
        ));

        if ($storeUrl === '' || $storeUrl === '#') {
            $storeUrl = $platform === 'ios'
                ? 'https://apps.apple.com'
                : 'https://play.google.com/store/apps/details?id=com.stylebite.app';
        }

        $belowMin = $this->compareVersions($clientVersion, $minVersion) < 0;
        $updateRequired = $forceUpdate && $belowMin && $minVersion !== '0.0.0';

        return response()->json([
            'status_code' => 1,
            'message' => $updateRequired
                ? 'A required app update is available.'
                : 'App version is up to date.',
            'platform' => $platform,
            'app_version' => $clientVersion,
            'min_version' => $minVersion,
            'force_update' => $forceUpdate,
            'update_required' => $updateRequired,
            'store_url' => $storeUrl,
            'title' => 'Update required',
            'body' => 'A new version of StyleBite is available. Please update to continue.',
        ]);
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '') {
            return '0.0.0';
        }

        // Accept "1.0.16+41" or "1.0.16 (41)" — compare marketing version only.
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
    private function compareVersions(string $left, string $right): int
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
}
