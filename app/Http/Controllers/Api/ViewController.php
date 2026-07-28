<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViewController extends Controller
{
    // Hard ceiling for a single view's watch time when the post has no known
    // video duration (e.g. images), so watch hours can't be farmed.
    private const MAX_WATCH_SECONDS_NO_DURATION = 600;

    /**
     * Ingest a batch of post views + watch time. Drives view counts and the
     * watch-hours used for ad eligibility. Watch seconds are capped at the
     * post's video duration so they can't be inflated.
     */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'views' => ['required', 'array', 'min:1', 'max:200'],
            'views.*.post_id' => ['required', 'integer'],
            'views.*.watch_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'views.*.view_source' => ['nullable', 'string', 'in:feed,reel,detail,explore,profile'],
        ]);

        $user = $request->user();
        $deviceId = $request->header('X-Device-Id');

        // Load the referenced posts + their max video duration in one pass.
        $postIds = collect($validated['views'])->pluck('post_id')->unique()->all();
        $durations = Post::query()
            ->whereIn('id', $postIds)
            ->withMax(['media as max_duration' => fn ($q) => $q->where('media_type', 'video')], 'duration_seconds')
            ->pluck('max_duration', 'id');

        $recorded = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, $user, $deviceId, $durations, &$recorded, &$skipped) {
            $viewsByPost = [];

            foreach ($validated['views'] as $view) {
                $postId = (int) $view['post_id'];

                if (! $durations->has($postId)) {
                    $skipped++; // unknown/deleted post
                    continue;
                }

                $cap = $durations->get($postId);
                $cap = ($cap !== null && (int) $cap > 0) ? (int) $cap : self::MAX_WATCH_SECONDS_NO_DURATION;
                $watchSeconds = min((int) $view['watch_seconds'], $cap);

                PostView::create([
                    'post_id' => $postId,
                    'viewer_user_id' => $user->id,
                    'device_id' => $deviceId,
                    'view_source' => $view['view_source'] ?? 'reel',
                    'watch_seconds' => $watchSeconds,
                    'created_at' => now(),
                ]);

                $viewsByPost[$postId] = ($viewsByPost[$postId] ?? 0) + 1;
                $recorded++;
            }

            foreach ($viewsByPost as $postId => $count) {
                Post::whereKey($postId)->increment('view_count', $count);
            }
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Views recorded successfully.',
            'recorded' => $recorded,
            'skipped' => $skipped,
        ]);
    }
}
