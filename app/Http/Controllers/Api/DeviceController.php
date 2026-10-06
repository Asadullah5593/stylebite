<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function storePushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['required', 'string', 'in:ios,android,web,desktop'],
            'push_token' => ['required', 'string', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ], [
            'device_id.required' => 'Device ID is required when a push token is provided.',
            'platform.in' => 'Please choose a valid platform.',
            'push_token.required' => 'Push token is required for this request.',
        ]);

        $deviceToken = DeviceToken::syncForUser(
            $request->user(),
            $validated['device_id'],
            $validated['platform'],
            $validated['push_token'],
            $validated['app_version'] ?? null,
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Push token saved successfully.',
            'device' => [
                'id' => $deviceToken->id,
                'device_id' => $deviceToken->device_id,
                'platform' => $deviceToken->platform,
                'app_version' => $deviceToken->app_version,
                'is_active' => (bool) $deviceToken->is_active,
            ],
        ]);
    }
}
