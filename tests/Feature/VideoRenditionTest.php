<?php

namespace Tests\Feature;

use App\Services\MediaOptimizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Video renditions were 720p at the cheapest encoder tier, so a portrait clip
 * came out 406px wide and was stretched almost 3x across the phone. These pin
 * the settings chosen from a side-by-side on 2026-09-14, and prove a portrait
 * source actually lands at 608x1080 through the real ffmpeg path.
 */
class VideoRenditionTest extends TestCase
{
    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        if (Process::run(['ffmpeg', '-version'])->failed()) {
            $this->markTestSkipped('ffmpeg is not installed here.');
        }

        $this->scratch = 'uploads/_test_'.bin2hex(random_bytes(4));
        File::makeDirectory(base_path($this->scratch), 0775, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->scratch));

        parent::tearDown();
    }

    public function test_video_settings_are_the_ones_chosen_from_the_comparison(): void
    {
        $this->assertSame(1080, MediaOptimizer::VIDEO_MAX_HEIGHT);
        $this->assertSame('medium', MediaOptimizer::VIDEO_PRESET);
        $this->assertSame('21', MediaOptimizer::VIDEO_CRF);
        $this->assertSame('3500k', MediaOptimizer::VIDEO_MAX_BITRATE);
    }

    public function test_a_portrait_clip_is_rendered_at_1080_tall_with_a_poster(): void
    {
        // A synthetic 1080x1920 portrait clip, like a phone recording.
        $source = $this->scratch.'/clip.mp4';
        $made = Process::run([
            'ffmpeg', '-nostdin', '-y', '-v', 'error',
            '-f', 'lavfi', '-i', 'testsrc2=size=1080x1920:rate=24:duration=1',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-shortest',
            base_path($source),
        ]);
        $this->assertTrue($made->successful(), $made->errorOutput());

        $rendition = app(MediaOptimizer::class)->transcodeStoredVideo($source);

        $this->assertNotNull($rendition);
        $this->assertSame(1080, $rendition['height']);
        $this->assertSame(608, $rendition['width']);
        $this->assertFileExists(base_path($rendition['path']));
        $this->assertFileExists(base_path($rendition['poster_path']));
        $this->assertStringEndsWith('.mp4', $rendition['path']);
    }

    public function test_a_clip_already_shorter_than_1080_is_not_upscaled(): void
    {
        $source = $this->scratch.'/small.mp4';
        Process::run([
            'ffmpeg', '-nostdin', '-y', '-v', 'error',
            '-f', 'lavfi', '-i', 'testsrc2=size=406x720:rate=24:duration=1',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-an',
            base_path($source),
        ]);

        $rendition = app(MediaOptimizer::class)->transcodeStoredVideo($source);

        $this->assertNotNull($rendition);
        $this->assertSame(720, $rendition['height']);
        $this->assertSame(406, $rendition['width']);
    }
}
