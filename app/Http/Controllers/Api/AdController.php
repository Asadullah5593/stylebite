<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdImpression;
use App\Models\Post;
use App\Services\CurrencyConverter;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdController extends Controller
{
    // Reject a single impression whose reported revenue exceeds this (base
    // currency) — a guard against spoofed/garbage client values.
    private const MAX_REVENUE_PER_IMPRESSION = 10.0;

    /**
     * Ad eligibility + current ads toggle + the config the app needs to render
     * the monetization screen and trigger mid-reel ads.
     */
    public function eligibility(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('profile');
        $eligibility = stylebite_ad_eligibility($user->id);

        return response()->json([
            'status_code' => 1,
            'message' => 'Ad eligibility fetched successfully.',
            'data' => array_merge($eligibility, [
                'ads_enabled' => (bool) ($user->profile?->ads_enabled ?? false),
                'config' => [
                    'reel_owner_share_percent' => (float) stylebite_app_config('ads.reel_owner_share_percent', 30),
                    'mid_reel_trigger_percent' => (float) stylebite_app_config('ads.mid_reel_trigger_percent', 30),
                ],
            ]),
        ]);
    }

    /**
     * Turn ads on/off for the creator's reels. Enabling requires meeting the
     * eligibility criteria; disabling is always allowed.
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $enabled = (bool) $validated['enabled'];

        if ($enabled) {
            $eligibility = stylebite_ad_eligibility($user->id);

            if (! $eligibility['eligible']) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'You do not meet the ad eligibility criteria yet.',
                    'data' => $eligibility,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $profile->forceFill([
            'ads_enabled' => $enabled,
            'ads_enabled_at' => $enabled ? now() : $profile->ads_enabled_at,
        ])->save();

        return response()->json([
            'status_code' => 1,
            'message' => $enabled ? 'Ads enabled on your reels.' : 'Ads disabled on your reels.',
            'ads_enabled' => $enabled,
        ]);
    }

    /**
     * Ingest ad impressions with their AdMob paid-event revenue. Scroll ads are
     * 100% platform (admin). Mid-reel ads are tied to a reel and split with its
     * owner (when the owner has ads enabled) at the admin-configured percentage.
     * Impressions are stored 'pending'; a scheduled command credits owners in
     * batches. De-dup is by impression_ref; absurd revenue is rejected.
     */
    public function impressions(Request $request, CurrencyConverter $converter): JsonResponse
    {
        $validated = $request->validate([
            'impressions' => ['required', 'array', 'min:1', 'max:200'],
            'impressions.*.ad_type' => ['required', 'string', 'in:scroll,mid_reel'],
            'impressions.*.post_id' => ['nullable', 'integer', 'required_if:impressions.*.ad_type,mid_reel'],
            'impressions.*.ad_unit_id' => ['nullable', 'string', 'max:191'],
            'impressions.*.impression_ref' => ['nullable', 'string', 'max:191'],
            'impressions.*.revenue' => ['required', 'numeric', 'min:0'],
            'impressions.*.currency' => ['nullable', 'string', 'size:3'],
        ], [
            'impressions.*.post_id.required_if' => 'post_id is required for mid_reel ads.',
        ]);

        $viewerId = $request->user()->id;
        $baseCurrency = $converter->baseCurrency();
        $sharePercent = max(0, min(100, (float) stylebite_app_config('ads.reel_owner_share_percent', 30)));

        // Resolve reel owners (only reels with ads enabled earn a share).
        $postIds = collect($validated['impressions'])
            ->where('ad_type', 'mid_reel')
            ->pluck('post_id')
            ->filter()
            ->unique()
            ->all();

        $owners = Post::query()
            ->whereIn('id', $postIds)
            ->with('user.profile:user_id,ads_enabled')
            ->get()
            ->keyBy('id');

        // Skip refs we've already recorded (idempotent retries).
        $refs = collect($validated['impressions'])->pluck('impression_ref')->filter()->unique()->all();
        $existingRefs = $refs === []
            ? collect()
            : AdImpression::query()->whereIn('impression_ref', $refs)->pluck('impression_ref')->flip();

        $accepted = 0;
        $duplicates = 0;
        $rejected = 0;

        foreach ($validated['impressions'] as $imp) {
            $ref = $imp['impression_ref'] ?? null;

            if ($ref !== null && $existingRefs->has($ref)) {
                $duplicates++;
                continue;
            }

            $revenue = round((float) $imp['revenue'], 8);
            $currency = strtoupper($imp['currency'] ?? 'USD');

            // Sanity cap in the base currency (convert weak currencies first so
            // legitimate impressions aren't wrongly rejected).
            $inBase = $converter->convert($revenue, $currency, $baseCurrency);
            $revenueInBase = $inBase !== null ? (float) $inBase['amount'] : $revenue;

            if ($revenueInBase > self::MAX_REVENUE_PER_IMPRESSION) {
                if ($this->storeImpression($imp, $viewerId, null, 0, 0, $revenue, $currency, 'rejected')) {
                    $rejected++;
                    if ($ref !== null) {
                        $existingRefs->put($ref, true);
                    }
                } else {
                    $duplicates++;
                }
                continue;
            }

            $ownerUserId = null;
            $ownerShare = 0.0;
            $appliedPercent = 0.0;

            if ($imp['ad_type'] === 'mid_reel') {
                $post = $owners->get((int) $imp['post_id']);
                $ownerEnabled = (bool) ($post?->user?->profile?->ads_enabled ?? false);

                // Owners don't earn from ads shown to themselves.
                if ($post && $ownerEnabled && (int) $post->user_id !== $viewerId) {
                    $ownerUserId = (int) $post->user_id;
                    $appliedPercent = $sharePercent;
                    $ownerShare = round($revenue * $sharePercent / 100, 8);
                }
            }

            // Only rows with a real owner share need settling later; everything
            // else (scroll, ads-off owner, self-view, 0% share) is final now.
            $status = ($ownerUserId !== null && $ownerShare > 0) ? 'pending' : 'settled';

            if ($this->storeImpression($imp, $viewerId, $ownerUserId, $appliedPercent, $ownerShare, $revenue, $currency, $status)) {
                $accepted++;
                if ($ref !== null) {
                    $existingRefs->put($ref, true);
                }
            } else {
                $duplicates++; // lost the unique-ref race with a concurrent request
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Impressions recorded successfully.',
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
        ]);
    }

    /**
     * Insert one impression. Returns false when a concurrent request already
     * inserted the same impression_ref (unique violation) so the caller counts
     * it as a duplicate instead of 500-ing the whole batch.
     */
    private function storeImpression(array $imp, int $viewerId, ?int $ownerUserId, float $percent, float $ownerShare, float $revenue, string $currency, string $status): bool
    {
        try {
            AdImpression::create([
                'user_id' => $viewerId,
                'post_id' => $imp['ad_type'] === 'mid_reel' ? ($imp['post_id'] ?? null) : null,
                'owner_user_id' => $ownerUserId,
                'ad_type' => $imp['ad_type'],
                'ad_unit_id' => $imp['ad_unit_id'] ?? null,
                'impression_ref' => $imp['impression_ref'] ?? null,
                'revenue' => $revenue,
                'currency_code' => $currency,
                'share_percent' => $percent,
                'owner_share' => $ownerShare,
                'admin_share' => round($revenue - $ownerShare, 8),
                'status' => $status,
                'viewed_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) { // duplicate key
                return false;
            }

            throw $e;
        }

        return true;
    }
}
