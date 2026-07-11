<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Produces mobile-friendly renditions of uploaded media.
 *
 * Images are recompressed and downscaled (Imagick, with a GD fallback).
 * Videos are transcoded to <=720p H.264 at a mobile-friendly bitrate via
 * ffmpeg, together with a lightweight JPEG poster frame so feeds can render a
 * still while scrolling instead of decoding full video.
 *
 * All paths are local, relative to base_path(), matching stylebite_upload_file().
 */
class MediaOptimizer
{
    public const FEED_IMAGE_MAX_DIMENSION = 1080;

    public const FEED_IMAGE_QUALITY = 72;

    public const AVATAR_MAX_DIMENSION = 512;

    public const AVATAR_QUALITY = 82;

    public const VIDEO_MAX_HEIGHT = 720;

    public const VIDEO_MAX_BITRATE = '2000k';

    public const VIDEO_BUFSIZE = '3000k';

    public const VIDEO_AUDIO_BITRATE = '128k';

    public const VIDEO_TIMEOUT_SECONDS = 900;

    /**
     * Compress + downscale a stored image. Returns rendition metadata or null.
     */
    public function optimizeStoredImage(string $relativeSourcePath, int $maxDimension, int $quality): ?array
    {
        $absoluteSource = $this->absolutePath($relativeSourcePath);

        if ($absoluteSource === null || ! File::exists($absoluteSource)) {
            return null;
        }

        $destFolder = $this->renditionFolder($relativeSourcePath);

        return $this->storeOptimizedImageFromPath($absoluteSource, $destFolder, $maxDimension, $quality);
    }

    /**
     * Compress + downscale an image at an absolute path (e.g. a freshly
     * uploaded temp file) into $destFolder. Returns rendition metadata or null.
     */
    public function storeOptimizedImageFromPath(
        string $absoluteSourcePath,
        string $destFolder,
        int $maxDimension,
        int $quality,
        ?string $preferredExtension = null
    ): ?array {
        if (! File::exists($absoluteSourcePath)) {
            return null;
        }

        $destFolder = trim($destFolder, '/');
        $absoluteDestFolder = base_path($destFolder);

        if (! File::exists($absoluteDestFolder)) {
            File::makeDirectory($absoluteDestFolder, 0755, true);
        }

        $extension = $this->imageExtensionFor($preferredExtension ?? $absoluteSourcePath);
        $fileName = Str::uuid()->toString().'.'.$extension;
        $absoluteDest = $absoluteDestFolder.'/'.$fileName;

        try {
            $dimensions = extension_loaded('imagick')
                ? $this->renderImageWithImagick($absoluteSourcePath, $absoluteDest, $maxDimension, $quality)
                : $this->renderImageWithGd($absoluteSourcePath, $absoluteDest, $maxDimension, $quality);
        } catch (\Throwable $exception) {
            Log::warning('Image optimization failed.', [
                'source' => $absoluteSourcePath,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($dimensions === null || ! File::exists($absoluteDest)) {
            return null;
        }

        $storedPath = $destFolder.'/'.$fileName;

        return [
            'path' => $storedPath,
            'url' => $this->assetUrl($storedPath),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'size_bytes' => File::size($absoluteDest),
        ];
    }

    /**
     * Whether this host can run ffmpeg. Shared hosting typically disables
     * proc_open (which the Process runner needs), so video transcoding is
     * impossible there and we serve the original untouched.
     */
    public function canTranscodeVideo(): bool
    {
        return $this->functionEnabled('proc_open');
    }

    /**
     * Transcode a stored video to <=720p H.264 and extract a poster frame.
     * Returns rendition metadata (incl. poster_path/poster_url) or null.
     */
    public function transcodeStoredVideo(string $relativeSourcePath): ?array
    {
        if (! $this->canTranscodeVideo()) {
            Log::info('Video transcoding skipped: shell/proc_open is unavailable on this host.', [
                'source' => $relativeSourcePath,
            ]);

            return null;
        }

        $absoluteSource = $this->absolutePath($relativeSourcePath);

        if ($absoluteSource === null || ! File::exists($absoluteSource)) {
            return null;
        }

        $probe = $this->probeVideo($absoluteSource);
        $sourceHeight = $probe['height'] ?? self::VIDEO_MAX_HEIGHT;
        $targetHeight = max(2, min(self::VIDEO_MAX_HEIGHT, $sourceHeight));
        $targetHeight -= $targetHeight % 2; // libx264 requires even dimensions.

        $destFolder = $this->renditionFolder($relativeSourcePath);
        $absoluteDestFolder = base_path($destFolder);

        if (! File::exists($absoluteDestFolder)) {
            File::makeDirectory($absoluteDestFolder, 0755, true);
        }

        $baseName = Str::uuid()->toString();
        $videoFileName = $baseName.'.mp4';
        $posterFileName = $baseName.'.jpg';
        $absoluteVideoDest = $absoluteDestFolder.'/'.$videoFileName;
        $absolutePosterDest = $absoluteDestFolder.'/'.$posterFileName;

        $transcode = Process::timeout(self::VIDEO_TIMEOUT_SECONDS)->run([
            'ffmpeg', '-y',
            '-i', $absoluteSource,
            '-vf', 'scale=-2:'.$targetHeight,
            '-c:v', 'libx264',
            '-profile:v', 'main',
            '-preset', 'veryfast',
            '-crf', '23',
            '-maxrate', self::VIDEO_MAX_BITRATE,
            '-bufsize', self::VIDEO_BUFSIZE,
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', self::VIDEO_AUDIO_BITRATE,
            '-movflags', '+faststart',
            $absoluteVideoDest,
        ]);

        if (! $transcode->successful() || ! File::exists($absoluteVideoDest)) {
            Log::warning('Video transcode failed.', [
                'source' => $absoluteSource,
                'error' => Str::limit($transcode->errorOutput(), 2000, ''),
            ]);

            return null;
        }

        // Poster frame from the transcoded output (already <=720p).
        $poster = Process::timeout(120)->run([
            'ffmpeg', '-y',
            '-i', $absoluteVideoDest,
            '-frames:v', '1',
            '-q:v', '3',
            $absolutePosterDest,
        ]);

        $optimizedProbe = $this->probeVideo($absoluteVideoDest);
        $storedVideoPath = $destFolder.'/'.$videoFileName;
        $storedPosterPath = $poster->successful() && File::exists($absolutePosterDest)
            ? $destFolder.'/'.$posterFileName
            : null;

        return [
            'path' => $storedVideoPath,
            'url' => $this->assetUrl($storedVideoPath),
            'width' => $optimizedProbe['width'] ?? $probe['width'] ?? null,
            'height' => $optimizedProbe['height'] ?? $targetHeight,
            'duration_seconds' => $optimizedProbe['duration'] ?? $probe['duration'] ?? null,
            'size_bytes' => File::size($absoluteVideoDest),
            'poster_path' => $storedPosterPath,
            'poster_url' => $storedPosterPath !== null ? $this->assetUrl($storedPosterPath) : null,
        ];
    }

    /**
     * @return array{width:int,height:int}|null
     */
    private function renderImageWithImagick(string $source, string $dest, int $maxDimension, int $quality): ?array
    {
        $imagick = new \Imagick($source);

        if ($imagick->getNumberImages() > 1) {
            $imagick = $imagick->coalesceImages()->getImage();
        }

        if (method_exists($imagick, 'autoOrient')) {
            $imagick->autoOrient();
        }

        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($width > $maxDimension || $height > $maxDimension) {
            // bestfit within a maxDimension square, preserving aspect ratio.
            $imagick->resizeImage($maxDimension, $maxDimension, \Imagick::FILTER_LANCZOS, 1, true);
        }

        $imagick->stripImage();
        $imagick->setImageCompressionQuality($quality);

        $format = Str::lower($imagick->getImageFormat() ?: '');

        if (in_array($format, ['jpeg', 'jpg'], true)) {
            $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE); // progressive JPEG
        }

        $imagick->writeImage($dest);

        $result = [
            'width' => $imagick->getImageWidth(),
            'height' => $imagick->getImageHeight(),
        ];

        $imagick->clear();
        $imagick->destroy();

        return $result;
    }

    /**
     * @return array{width:int,height:int}|null
     */
    private function renderImageWithGd(string $source, string $dest, int $maxDimension, int $quality): ?array
    {
        $info = @getimagesize($source);

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        $type = $info[2];

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            IMAGETYPE_GIF => @imagecreatefromgif($source),
            default => null,
        };

        if (! $image) {
            return null;
        }

        $scale = min(1, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $extension = Str::lower(pathinfo($dest, PATHINFO_EXTENSION));

        match ($extension) {
            'png' => imagepng($canvas, $dest, (int) round(9 - ($quality / 100 * 9))),
            'webp' => imagewebp($canvas, $dest, $quality),
            'gif' => imagegif($canvas, $dest),
            default => imagejpeg($canvas, $dest, $quality),
        };

        imagedestroy($image);
        imagedestroy($canvas);

        return ['width' => $targetWidth, 'height' => $targetHeight];
    }

    /**
     * @return array{width:?int,height:?int,duration:?int}
     */
    private function probeVideo(string $absolutePath): array
    {
        $result = Process::timeout(60)->run([
            'ffprobe', '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height:format=duration',
            '-of', 'json',
            $absolutePath,
        ]);

        if (! $result->successful()) {
            return ['width' => null, 'height' => null, 'duration' => null];
        }

        $payload = json_decode($result->output(), true);
        $stream = $payload['streams'][0] ?? [];
        $duration = $payload['format']['duration'] ?? null;

        return [
            'width' => isset($stream['width']) ? (int) $stream['width'] : null,
            'height' => isset($stream['height']) ? (int) $stream['height'] : null,
            'duration' => $duration !== null ? (int) round((float) $duration) : null,
        ];
    }

    private function imageExtensionFor(string $pathOrExtension): string
    {
        // Accept either a full path or a bare extension (e.g. "png").
        $extension = str_contains($pathOrExtension, '.')
            ? pathinfo($pathOrExtension, PATHINFO_EXTENSION)
            : $pathOrExtension;
        $extension = Str::lower(trim($extension));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
            ? $extension
            : 'jpg';
    }

    private function renditionFolder(string $relativeSourcePath): string
    {
        $directory = trim(str_replace('\\', '/', dirname($relativeSourcePath)), '/');

        return ($directory === '' || $directory === '.')
            ? 'optimized'
            : $directory.'/optimized';
    }

    private function functionEnabled(string $function): bool
    {
        if (! function_exists($function)) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array($function, $disabled, true);
    }

    private function absolutePath(string $relativePath): ?string
    {
        $relativePath = ltrim(trim($relativePath), '/');

        return $relativePath === '' ? null : base_path($relativePath);
    }

    private function assetUrl(string $storedPath): string
    {
        return rtrim((string) config('app.asset_url'), '/').'/'.ltrim($storedPath, '/');
    }
}
