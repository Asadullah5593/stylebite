<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\DeviceTokenRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Push-token registration outside the login flow.
 *
 * Until this existed, a token was only ever written while logging in. FCM rotates
 * tokens on its own — app reinstall, cleared storage, restore onto a new handset,
 * or Firebase's periodic refresh — and a user who stays signed in for weeks never
 * hits the login path again. The database kept the old string, every notification
 * to that device came back INVALID_ARGUMENT, and from the panel it looked like a
 * delivered campaign.
 *
 * The app should call this on every launch and from its onTokenRefresh handler.
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request, DeviceTokenRegistrar $registrar): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['required', 'string', 'in:ios,android,web'],
            'push_token' => ['required', 'string', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ], [
            'platform.in' => 'Push tokens are only supported for ios, android, or web.',
        ]);

        $deviceToken = $registrar->register(
            $request->user(),
            $validated['device_id'],
            $validated['platform'],
            $validated['push_token'],
            $validated['app_version'] ?? null,
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Push token registered successfully.',
            'device' => [
                'id' => $deviceToken->id,
                'device_id' => $deviceToken->device_id,
                'platform' => $deviceToken->platform,
            ],
        ]);
    }

    /**
     * Unregister without ending the session — for a user turning notifications
     * off in settings. Logging out is a separate call.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
        ]);

        $removed = DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('device_id', $validated['device_id'])
            ->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Push token removed successfully.',
            'push_token_removed' => $removed > 0,
        ]);
    }
}
