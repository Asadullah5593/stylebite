<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonetizationController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = stylebite_ad_eligibility((int) $user->id);

        return response()->json([
            'status_code' => 1,
            'message' => 'Monetization status fetched successfully.',
            'data' => $payload,
        ]);
    }

    public function requestAccess(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = Profile::query()->firstOrCreate(['user_id' => $user->id]);
        $eligibility = stylebite_ad_eligibility((int) $user->id);

        if (! ($eligibility['eligible'] ?? false)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You have not met the monetization criteria yet.',
                'data' => $eligibility,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = (string) ($profile->monetization_status ?? 'none');
        if ($status === 'pending') {
            return response()->json([
                'status_code' => 0,
                'message' => 'Your monetization request is already pending review.',
                'data' => $eligibility,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($status === 'approved' || (bool) $profile->ads_enabled) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Monetization is already enabled on your account.',
                'data' => $eligibility,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $profile->forceFill([
            'monetization_status' => 'pending',
            'monetization_requested_at' => now(),
            'monetization_reviewed_at' => null,
            'monetization_reviewed_by' => null,
            'monetization_admin_note' => null,
        ])->save();

        $fresh = stylebite_ad_eligibility((int) $user->id);

        return response()->json([
            'status_code' => 1,
            'message' => 'Monetization request submitted successfully.',
            'data' => $fresh,
        ]);
    }

    public function toggleAds(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $profile = Profile::query()->firstOrCreate(['user_id' => $user->id]);
        $status = (string) ($profile->monetization_status ?? 'none');
        $approved = $status === 'approved' || (bool) $profile->ads_enabled;

        if (! $approved && $validated['enabled']) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Monetization must be approved before enabling ads.',
                'data' => stylebite_ad_eligibility((int) $user->id),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $profile->forceFill([
            'ads_enabled' => (bool) $validated['enabled'],
        ])->save();

        return response()->json([
            'status_code' => 1,
            'message' => $validated['enabled'] ? 'Ads enabled successfully.' : 'Ads disabled.',
            'data' => stylebite_ad_eligibility((int) $user->id),
        ]);
    }
}
