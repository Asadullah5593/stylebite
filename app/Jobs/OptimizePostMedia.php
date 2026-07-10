<?php

namespace App\Jobs;

use App\Models\PostMedia;
use App\Services\MediaOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates the mobile-optimized rendition for a single post media row:
 * a compressed image, or a <=720p transcoded video with a poster frame.
 * Runs on the queue so uploads stay fast.
 */
class OptimizePostMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 1200;

    public function __construct(public int $postMediaId)
    {
    }

    public function handle(MediaOptimizer $optimizer): void
    {
        $media = PostMedia::query()->find($this->postMediaId);

        if ($media === null || $media->file_path === null) {
            return;
        }

        // Already optimized (e.g. re-dispatched) — nothing to do.
        if ($media->optimized_url !== null && $media->processing_status === 'ready') {
            return;
        }

        $media->forceFill(['processing_status' => 'processing'])->save();

        try {
            $media->media_type === 'video'
                ? $this->optimizeVideo($media, $optimizer)
                : $this->optimizeImage($media, $optimizer);
        } catch (\Throwable $exception) {
            $media->forceFill(['processing_status' => 'failed'])->save();

            Log::warning('Post media optimization failed.', [
                'post_media_id' => $media->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function optimizeImage(PostMedia $media, MediaOptimizer $optimizer): void
    {
        $rendition = $optimizer->optimizeStoredImage(
            $media->file_path,
            MediaOptimizer::FEED_IMAGE_MAX_DIMENSION,
            MediaOptimizer::FEED_IMAGE_QUALITY,
        );

        if ($rendition === null) {
            // Leave the original usable; just mark ready so the feed keeps serving it.
            $media->forceFill(['processing_status' => 'ready'])->save();

            return;
        }

        $media->forceFill([
            'optimized_path' => $rendition['path'],
            'optimized_url' => $rendition['url'],
            'optimized_width' => $rendition['width'],
            'optimized_height' => $rendition['height'],
            'optimized_size_bytes' => $rendition['size_bytes'],
            'width' => $media->width ?? $rendition['width'],
            'height' => $media->height ?? $rendition['height'],
            'processing_status' => 'ready',
            'optimized_at' => now(),
        ])->save();
    }

    private function optimizeVideo(PostMedia $media, MediaOptimizer $optimizer): void
    {
        $rendition = $optimizer->transcodeStoredVideo($media->file_path);

        if ($rendition === null) {
            $media->forceFill(['processing_status' => 'ready'])->save();

            return;
        }

        $media->forceFill([
            'optimized_path' => $rendition['path'],
            'optimized_url' => $rendition['url'],
            'optimized_width' => $rendition['width'],
            'optimized_height' => $rendition['height'],
            'optimized_size_bytes' => $rendition['size_bytes'],
            'duration_seconds' => $media->duration_seconds ?? $rendition['duration_seconds'],
            'thumbnail_url' => $rendition['poster_url'] ?? $media->thumbnail_url,
            'processing_status' => 'ready',
            'optimized_at' => now(),
        ])->save();
    }
}
