<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AppVersionGate;
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
        $clientVersion = AppVersionGate::normalizeVersion($validated['app_version']);
        $minVersion = AppVersionGate::minVersionForPlatform($platform);
        $forceUpdate = AppVersionGate::isForceEnabled();
        $storeUrl = AppVersionGate::storeUrlForPlatform($platform);

        $belowMin = AppVersionGate::compareVersions($clientVersion, $minVersion) < 0;
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
}
