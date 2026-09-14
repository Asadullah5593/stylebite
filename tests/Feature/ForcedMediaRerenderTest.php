<?php

namespace Tests\Feature;

use App\Jobs\OptimizePostMedia;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `stylebite:optimize-media --force` used to re-dispatch every row and then
 * every row returned untouched, because the job's own "already optimized"
 * guard did not know about --force. A change to the rendition settings
 * therefore never reached media uploaded before the change — which is the
 * one time anybody runs --force.
 */
class ForcedMediaRerenderTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_without_force_an_optimized_row_is_left_alone(): void
    {
        $media = $this->optimizedMedia();
        $before = $media->only(['optimized_path', 'optimized_width', 'optimized_height']);

        OptimizePostMedia::dispatchSync($media->id);

        $this->assertSame($before, $media->fresh()->only(['optimized_path', 'optimized_width', 'optimized_height']));
        $this->assertFileExists(base_path($before['optimized_path']));
    }

    public function test_force_re_renders_with_the_current_settings_and_drops_the_old_file(): void
    {
        $media = $this->optimizedMedia();
        $oldPath = $media->optimized_path;

        OptimizePostMedia::dispatchSync($media->id, force: true);

        $media->refresh();

        $this->assertNotSame($oldPath, $media->optimized_path);
        $this->assertSame(1200, $media->optimized_width);
        $this->assertSame(1600, $media->optimized_height);
        $this->assertSame('ready', $media->processing_status);
        $this->assertFileExists(base_path($media->optimized_path));
        $this->assertFileDoesNotExist(base_path($oldPath));
    }

    public function test_the_command_passes_force_through_and_can_target_images_only(): void
    {
        $image = $this->optimizedMedia();
        $video = $this->optimizedMedia(['media_type' => 'video', 'file_path' => $this->scratch.'/clip.mp4']);

        $this->artisan('stylebite:optimize-media', ['--force' => true, '--sync' => true, '--type' => 'image'])
            ->assertSuccessful();

        $this->assertSame(1200, $image->fresh()->optimized_width);
        // The video row was outside --type=image, so it was never touched.
        $this->assertSame(200, $video->fresh()->optimized_width);
    }

    public function test_the_command_rejects_an_unknown_type(): void
    {
        $this->artisan('stylebite:optimize-media', ['--force' => true, '--type' => 'gif'])
            ->assertFailed();
    }

    /** A media row that already has a (deliberately small, stale) rendition on disk. */
    private function optimizedMedia(array $overrides = []): PostMedia
    {
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Re-render fixture',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'published_at' => now(),
        ]);

        $source = $this->scratch.'/source-'.bin2hex(random_bytes(3)).'.jpg';
        $this->jpeg(base_path($source), 3000, 4000);

        $stale = $this->scratch.'/optimized/stale-'.bin2hex(random_bytes(3)).'.jpg';
        File::ensureDirectoryExists(dirname(base_path($stale)));
        $this->jpeg(base_path($stale), 200, 267);

        return PostMedia::create(array_merge([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_path' => $source,
            'file_url' => 'https://example.com/'.$source,
            'optimized_path' => $stale,
            'optimized_url' => 'https://example.com/'.$stale,
            'optimized_width' => 200,
            'optimized_height' => 267,
            'processing_status' => 'ready',
            'optimized_at' => now()->subDay(),
        ], $overrides));
    }

    private function jpeg(string $absolute, int $width, int $height): void
    {
        $canvas = imagecreatetruecolor($width, $height);

        for ($i = 0; $i < 300; $i++) {
            imagefilledellipse(
                $canvas, rand(0, $width), rand(0, $height), rand(20, 300), rand(20, 300),
                imagecolorallocate($canvas, rand(0, 255), rand(0, 255), rand(0, 255)),
            );
        }

        imagejpeg($canvas, $absolute, 90);
        imagedestroy($canvas);
    }
}
