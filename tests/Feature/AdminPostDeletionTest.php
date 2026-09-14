<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deleting a post from the admin panel, and showing its media while you decide.
 *
 * The media half is here rather than in a view-only smoke test because the bug
 * it covers was invisible to "does the page render": the pages rendered fine,
 * they just printed a stale absolute URL instead of the picture.
 */
class AdminPostDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function makePost(?User $author = null, array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id' => ($author ?? User::factory()->create())->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Admin deletion fixture',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_deleting_a_post_requires_a_reason_and_records_it(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        // No reason, no deletion — the confirmation dialog is not the control.
        $this->actingAs($admin)
            ->delete(route('admin.posts.destroy', $post))
            ->assertSessionHasErrors('reason');

        $this->assertNull($post->fresh()->deleted_at);

        $this->actingAs($admin)
            ->delete(route('admin.posts.destroy', $post), [
                'reason' => 'Reported for reusing another creator\'s photos.',
            ])
            ->assertRedirect(route('admin.posts.all_posts'));

        $deleted = Post::withTrashed()->find($post->id);
        $this->assertNotNull($deleted->deleted_at);
        // Marked removed as well as trashed, so anything reading `status`
        // without the soft-delete scope still treats it as taken down.
        $this->assertSame('removed', $deleted->status);
        $this->assertNull(Post::find($post->id));

        $this->assertDatabaseHas((new ModerationAction)->getTable(), [
            'target_type' => 'post',
            'target_id' => $post->id,
            'action' => 'remove',
            'reason' => 'Reported for reusing another creator\'s photos.',
        ]);

        $this->assertDatabaseHas((new ActivityLog)->getTable(), [
            'event_name' => 'post_deleted',
            'entity_type' => 'post',
            'entity_id' => $post->id,
        ]);
    }

    public function test_a_deleted_post_can_be_found_and_restored_under_review(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();
        // A live post that must never appear under the Deleted filter.
        $this->makePost(null, ['caption' => 'Still live and must not be listed']);

        $this->actingAs($admin)->delete(route('admin.posts.destroy', $post), [
            'reason' => 'Deleted so the restore path can be exercised.',
        ]);

        // The default list must not show it; the Deleted filter must.
        $this->actingAs($admin)
            ->get(route('admin.posts.all_posts'))
            ->assertOk()
            ->assertDontSee('Admin deletion fixture');

        // Not just "the deleted post is present" — the earlier version of this
        // used withTrashed(), which also returned every live post, so the
        // filter looked completely ignored while still passing that assertion.
        $this->actingAs($admin)
            ->get(route('admin.posts.all_posts', ['status' => 'deleted']))
            ->assertOk()
            ->assertSee('Admin deletion fixture')
            ->assertDontSee('Still live and must not be listed');

        // The detail page has to stay reachable, or "restore" has nowhere to live.
        $this->actingAs($admin)
            ->get(route('admin.posts.show', $post->id))
            ->assertOk();

        $this->actingAs($admin)
            ->patch(route('admin.posts.restore', $post->id))
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.posts.all_posts', ['status' => 'deleted']))
            ->assertOk()
            ->assertDontSee('Admin deletion fixture');

        $restored = Post::find($post->id);
        $this->assertNotNull($restored, 'A restored post should be back in the default scope.');
        // Deliberately not straight back into the feeds.
        $this->assertSame('under_review', $restored->status);

        $this->assertDatabaseHas((new ModerationAction)->getTable(), [
            'target_type' => 'post',
            'target_id' => $post->id,
            'action' => 'restore',
        ]);
    }

    public function test_media_of_a_deleted_post_still_names_its_post(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_path' => 'posts/7/orphan.jpg',
            'file_url' => 'https://example.com/posts/7/orphan.jpg',
            'processing_status' => 'ready',
        ]);

        $this->actingAs($admin)->delete(route('admin.posts.destroy', $post), [
            'reason' => 'Deleted while its media row stays in the media list.',
        ]);

        // The row outlives the post, so it must not silently read "no caption".
        $this->actingAs($admin)
            ->get(route('admin.posts.post_media'))
            ->assertOk()
            ->assertSee('Admin deletion fixture')
            ->assertDontSee('Post no longer exists');
    }

    public function test_deleting_a_post_is_gated_on_posts_delete_not_posts_moderate(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator', 'status' => 'active']);
        $moderator->assignRole(Role::findByName('content_moderator', 'web'));
        $post = $this->makePost();

        $this->actingAs($moderator)
            ->delete(route('admin.posts.destroy', $post), ['reason' => 'Should not be allowed.'])
            ->assertForbidden();

        $this->assertNull($post->fresh()->deleted_at);

        // ...and the button is not dangled in front of them either.
        $this->actingAs($moderator)
            ->get(route('admin.posts.show', $post))
            ->assertOk()
            ->assertDontSee('admin.posts.destroy');
    }

    public function test_post_media_is_rendered_from_the_current_host_not_the_url_baked_at_upload(): void
    {
        config(['app.asset_url' => 'https://current.example.com']);

        $admin = $this->admin();
        $post = $this->makePost();

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_path' => 'posts/7/a-photo.jpg',
            // Baked under a host this install no longer serves.
            'file_url' => 'http://stylebiteapp.com/posts/7/a-photo.jpg',
            'processing_status' => 'ready',
        ]);

        $expected = 'https://current.example.com/posts/7/a-photo.jpg';

        foreach ([
            route('admin.posts.all_posts'),
            route('admin.posts.show', $post),
            route('admin.posts.post_media'),
        ] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee($expected, false)
                ->assertDontSee('stylebiteapp.com/posts/7/a-photo.jpg', false);
        }
    }

    public function test_video_previews_link_to_the_transcoded_rendition_not_the_quicktime_upload(): void
    {
        config(['app.asset_url' => 'https://current.example.com']);

        $admin = $this->admin();
        $post = $this->makePost();

        // The shape live actually has: a .mov/quicktime upload that browsers
        // will not play, with an H.264 MP4 rendition sitting beside it.
        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_path' => 'posts/78/clip.mov',
            'file_url' => 'https://stylebiteapp.com/posts/78/clip.mov',
            'mime_type' => 'video/quicktime',
            'optimized_path' => 'posts/78/optimized/clip.mp4',
            'optimized_url' => 'https://aws.stylebiteapp.com/posts/78/optimized/clip.mp4',
            'thumbnail_url' => 'posts/78/optimized/clip.jpg',
            'processing_status' => 'ready',
        ]);

        // The thumbnail's own anchor is the thing a moderator clicks, so pin that
        // one rather than "the mp4 appears somewhere on the page".
        $thumbnailAnchor = 'href="https://current.example.com/posts/78/optimized/clip.mp4"'
            .' target="_blank" rel="noopener" class="d-block h-100';

        foreach ([
            route('admin.posts.all_posts'),
            route('admin.posts.show', $post),
            route('admin.posts.post_media'),
        ] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee($thumbnailAnchor, false);
        }

        // All Posts has no "original" affordance, so the unplayable .mov must
        // not be linked there at all.
        $this->actingAs($admin)
            ->get(route('admin.posts.all_posts'))
            ->assertOk()
            ->assertDontSee('clip.mov', false);

        // On the media list the original stays reachable for moderation, but
        // only as a separate, labelled link — never as the preview.
        $this->actingAs($admin)
            ->get(route('admin.posts.post_media'))
            ->assertOk()
            ->assertSee('https://current.example.com/posts/78/clip.mov', false)
            ->assertSee('Original upload');
    }

    public function test_a_video_without_a_rendition_still_falls_back_to_the_original(): void
    {
        config(['app.asset_url' => 'https://current.example.com']);

        $admin = $this->admin();
        $post = $this->makePost();

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_path' => 'posts/78/untranscoded.mp4',
            'file_url' => 'https://stylebiteapp.com/posts/78/untranscoded.mp4',
            'optimized_path' => null,
            'optimized_url' => null,
            'processing_status' => 'ready',
        ]);

        // No rendition (transcode failed, or the row predates the pipeline):
        // a sound-only preview still beats no preview at all.
        $this->actingAs($admin)
            ->get(route('admin.posts.post_media'))
            ->assertOk()
            ->assertSee('https://current.example.com/posts/78/untranscoded.mp4', false)
            ->assertDontSee('Original upload');
    }

    public function test_images_keep_pointing_at_the_original_for_full_fidelity(): void
    {
        config(['app.asset_url' => 'https://current.example.com']);

        $admin = $this->admin();
        $post = $this->makePost();

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_path' => 'posts/78/photo.jpg',
            'file_url' => 'https://stylebiteapp.com/posts/78/photo.jpg',
            'optimized_path' => 'posts/78/optimized/photo.jpg',
            'optimized_url' => 'https://stylebiteapp.com/posts/78/optimized/photo.jpg',
            'processing_status' => 'ready',
        ]);

        // Images render everywhere, and moderation wants what was uploaded.
        $this->actingAs($admin)
            ->get(route('admin.posts.post_media'))
            ->assertOk()
            ->assertSee('https://current.example.com/posts/78/photo.jpg', false)
            ->assertDontSee('Original upload');
    }

    public function test_media_without_a_relative_path_still_falls_back_to_its_stored_url(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'video',
            'media_role' => 'original',
            'file_path' => null,
            'file_url' => 'https://cdn.example.com/clips/reel.mp4',
            'processing_status' => 'ready',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.posts.post_media'))
            ->assertOk()
            ->assertSee('https://cdn.example.com/clips/reel.mp4', false);
    }
}
