<?php

namespace Tests\Feature;

use App\Services\MediaOptimizer;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The feed rendition used to be 1080px at quality 72. A portrait photo came out
 * 810px wide — narrower than a phone screen — so every photo was upscaled by the
 * device and looked soft. These tests pin the settings chosen from a side-by-side
 * on 2026-09-14 so a future "make it smaller" tweak cannot quietly undo them.
 */
class FeedImageRenditionTest extends TestCase
{
    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = 'uploads/_test_'.bin2hex(random_bytes(4));
        File::makeDirectory(base_path($this->scratch), 0775, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->scratch));

        parent::tearDown();
    }

    public function test_feed_settings_are_the_ones_chosen_from_the_comparison(): void
    {
        $this->assertSame(1600, MediaOptimizer::FEED_IMAGE_MAX_DIMENSION);
        $this->assertSame(85, MediaOptimizer::FEED_IMAGE_QUALITY);
    }

    public function test_a_portrait_upload_keeps_at_least_phone_width(): void
    {
        // 3:4 portrait, like nearly every outfit photo.
        $source = $this->jpeg(3000, 4000);

        $rendition = app(MediaOptimizer::class)->optimizeStoredImage(
            $source,
            MediaOptimizer::FEED_IMAGE_MAX_DIMENSION,
            MediaOptimizer::FEED_IMAGE_QUALITY,
        );

        $this->assertNotNull($rendition);
        $this->assertSame(1600, $rendition['height']);
        // 1200 wide is the point of the change: current phones are 1170–1290px.
        $this->assertSame(1200, $rendition['width']);
        $this->assertFileExists(base_path($rendition['path']));
        $this->assertLessThan(File::size(base_path($source)), $rendition['size_bytes']);
    }

    public function test_an_image_already_within_bounds_is_not_upscaled(): void
    {
        $source = $this->jpeg(800, 600);

        $rendition = app(MediaOptimizer::class)->optimizeStoredImage(
            $source,
            MediaOptimizer::FEED_IMAGE_MAX_DIMENSION,
            MediaOptimizer::FEED_IMAGE_QUALITY,
        );

        $this->assertNotNull($rendition);
        $this->assertSame(800, $rendition['width']);
        $this->assertSame(600, $rendition['height']);
    }

    /** Write a detailed JPEG so the encoder has real work to do; returns the relative path. */
    private function jpeg(int $width, int $height): string
    {
        $canvas = imagecreatetruecolor($width, $height);

        for ($i = 0; $i < 600; $i++) {
            imagefilledellipse(
                $canvas, rand(0, $width), rand(0, $height), rand(20, 300), rand(20, 300),
                imagecolorallocate($canvas, rand(0, 255), rand(0, 255), rand(0, 255)),
            );
        }

        $relative = $this->scratch.'/source.jpg';
        imagejpeg($canvas, base_path($relative), 95);
        imagedestroy($canvas);

        return $relative;
    }
}
