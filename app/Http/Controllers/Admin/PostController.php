<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostRating;
use App\Models\PostTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    private const POST_TYPES = ['outfit', 'food', 'reel', 'memory', 'contest_submission'];
    private const CONTENT_TYPES = ['fashion', 'food', 'mixed', 'text_only'];
    private const MEDIA_KINDS = ['none', 'image', 'video', 'carousel', 'mixed'];
    private const FEED_TYPES = ['style', 'bite'];
    private const VISIBILITIES = ['public', 'private', 'followers_only'];
    private const STATUSES = ['draft', 'published', 'archived', 'under_review', 'removed'];
    private const MODERATION_STATUSES = ['clean', 'flagged', 'restricted', 'blocked'];

    public function index(Request $request): View
    {
        $posts = Post::query()
            ->with('user:id,username,full_name')
            ->withCount(['media', 'tags'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('caption', 'like', "%{$search}%")
                        ->orWhere('location_name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('post_type'), fn ($query) => $query->where('post_type', $request->string('post_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.posts.AllPostsPage', compact('posts'));
    }

    public function show(Post $post): View
    {
        $post->load([
            'user:id,username,full_name,email',
            'media',
            'tags:id,name',
            'ratings.user:id,username,full_name',
            'comments.user:id,username,full_name',
        ]);

        return view('admin.posts.ShowPostPage', compact('post'));
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.EditPostPage', compact('post'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'caption' => ['nullable', 'string'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'dish_name' => ['nullable', 'string', 'max:191'],
            'restaurant' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'post_type' => ['required', Rule::in(self::POST_TYPES)],
            'content_type' => ['required', Rule::in(self::CONTENT_TYPES)],
            'media_kind' => ['required', Rule::in(self::MEDIA_KINDS)],
            'feed_type' => ['nullable', Rule::in(self::FEED_TYPES)],
            'visibility' => ['required', Rule::in(self::VISIBILITIES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'moderation_status' => ['required', Rule::in(self::MODERATION_STATUSES)],
            'allow_comments' => ['nullable', 'boolean'],
            'allow_shares' => ['nullable', 'boolean'],
            'rating_enabled' => ['nullable', 'boolean'],
        ]);

        $post->fill($data);
        $post->allow_comments = $request->boolean('allow_comments');
        $post->allow_shares = $request->boolean('allow_shares');
        $post->rating_enabled = $request->boolean('rating_enabled');
        $post->is_blocked = in_array($data['moderation_status'], ['restricted', 'blocked'], true);
        $post->is_reported = $data['moderation_status'] !== 'clean';

        if ($data['status'] === 'published' && ! $post->published_at) {
            $post->published_at = now();
        }

        $post->save();

        return redirect()
            ->route('admin.posts.show', $post)
            ->with('status', "Post #{$post->id} updated successfully.");
    }

    public function moderate(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'moderation_status' => ['required', Rule::in(self::MODERATION_STATUSES)],
        ]);

        $post->fill($data);
        $post->is_blocked = in_array($data['moderation_status'], ['restricted', 'blocked'], true);
        $post->is_reported = $data['moderation_status'] !== 'clean';

        if ($data['status'] === 'published' && ! $post->published_at) {
            $post->published_at = now();
        }

        $post->save();

        return back()->with('status', "Post #{$post->id} moderation updated successfully.");
    }

    public function media(Request $request): View
    {
        $media = PostMedia::query()
            ->with(['post:id,caption', 'upload:id,file_url,thumbnail_url'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('media_type', 'like', "%{$search}%")
                        ->orWhere('media_role', 'like', "%{$search}%")
                        ->orWhere('processing_status', 'like', "%{$search}%")
                        ->orWhereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('media_type'), fn ($query) => $query->where('media_type', $request->string('media_type')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.posts.PostMediaPage', compact('media'));
    }

    public function tags(Request $request): View
    {
        $postTags = PostTag::query()
            ->with(['post:id,caption', 'tag:id,name'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->whereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"))
                    ->orWhereHas('tag', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.posts.PostTagsPage', compact('postTags'));
    }

    public function ratings(Request $request): View
    {
        $ratings = PostRating::query()
            ->with(['post:id,caption', 'user:id,username,full_name'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->whereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($query) => $query
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%"));
            })
            ->when($request->filled('rating_value'), fn ($query) => $query->where('rating_value', $request->integer('rating_value')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.posts.PostRatingsPage', compact('ratings'));
    }

    public static function tabCounts(): array
    {
        return [
            'all_posts' => Post::count(),
            'post_media' => PostMedia::count(),
            'post_tags' => PostTag::count(),
            'post_ratings' => PostRating::count(),
        ];
    }
}
