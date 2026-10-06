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
    public function storeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'views' => ['required', 'array', 'min:1', 'max:50'],
            'views.*.post_id' => ['required', 'integer'],
            'views.*.watch_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'views.*.view_source' => ['nullable', 'string', 'in:feed,reel,detail,explore,profile'],
            'device_id' => ['nullable', 'string', 'max:191'],
        ]);

        $user = $request->user();
        $deviceId = $validated['device_id']
            ?? $request->attributes->get('auth_session')?->device_id;

        $recorded = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, $user, $deviceId, &$recorded, &$updated, &$skipped): void {
            foreach ($validated['views'] as $view) {
                $postId = (int) $view['post_id'];
                $watchSeconds = (int) $view['watch_seconds'];
                $source = $view['view_source'] ?? 'reel';

                if ($postId <= 0 || $watchSeconds <= 0) {
                    $skipped++;
                    continue;
                }

                $post = Post::query()
                    ->whereKey($postId)
                    ->where('status', 'published')
                    ->where('is_blocked', false)
                    ->first();

                if (! $post) {
                    $skipped++;
                    continue;
                }

                $existing = PostView::query()
                    ->where('post_id', $post->id)
                    ->where('viewer_user_id', $user->id)
                    ->first();

                if ($existing) {
                    $existing->forceFill([
                        'watch_seconds' => (int) $existing->watch_seconds + $watchSeconds,
                        'view_source' => $source,
                        'device_id' => $deviceId ?? $existing->device_id,
                    ])->save();
                    $updated++;
                    continue;
                }

                PostView::create([
                    'post_id' => $post->id,
                    'viewer_user_id' => $user->id,
                    'device_id' => $deviceId,
                    'view_source' => $source,
                    'watch_seconds' => $watchSeconds,
                    'created_at' => now(),
                ]);
                $post->increment('view_count');
                $recorded++;
            }
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Views recorded successfully.',
            'recorded' => $recorded,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }
}
