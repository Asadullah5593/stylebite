<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommentLike;
use App\Models\PostLike;
use App\Models\PostShare;
use App\Models\PostView;
use App\Models\ReplyLike;
use App\Models\SavedPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EngagementController extends Controller
{
    public function postLikes(Request $request): View
    {
        $likes = PostLike::query()
            ->with([
                'post.user:id,username,full_name',
                'user:id,username,full_name,avatar_url',
            ])
            ->when($request->filled('q'), fn ($query) => $this->applyPostUserSearch($query, $request->string('q')->toString()))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.engagement.PostLikesPage', compact('likes'));
    }

    public function commentLikes(Request $request): View
    {
        $likes = CommentLike::query()
            ->with([
                'comment.post:id,user_id,caption',
                'comment.user:id,username,full_name',
                'user:id,username,full_name,avatar_url',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('id', $search)
                        ->orWhereHas('comment', fn ($query) => $query->where('body', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.engagement.CommentLikesPage', compact('likes'));
    }

    public function replyLikes(Request $request): View
    {
        $likes = ReplyLike::query()
            ->with([
                'reply.comment.post:id,user_id,caption',
                'reply.user:id,username,full_name',
                'user:id,username,full_name,avatar_url',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('id', $search)
                        ->orWhereHas('reply', fn ($query) => $query->where('body', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.engagement.ReplyLikesPage', compact('likes'));
    }

    public function shares(Request $request): View
    {
        $shares = PostShare::query()
            ->with([
                'post.user:id,username,full_name',
                'user:id,username,full_name,avatar_url',
                'targetUser:id,username,full_name,avatar_url',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('id', $search)
                        ->orWhere('share_channel', 'like', "%{$search}%")
                        ->orWhereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('targetUser', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('share_channel'), fn ($query) => $query->where('share_channel', $request->string('share_channel')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.engagement.SharesPage', compact('shares'));
    }

    public function saved(Request $request): View
    {
        $savedPosts = SavedPost::query()
            ->with([
                'post.user:id,username,full_name',
                'user:id,username,full_name,avatar_url',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('id', $search)
                        ->orWhere('collection_name', 'like', "%{$search}%")
                        ->orWhereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.engagement.SavedPostsPage', compact('savedPosts'));
    }

    public function views(Request $request): View
    {
        $views = PostView::query()
            ->with([
                'post.user:id,username,full_name',
                'viewer:id,username,full_name,avatar_url',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('id', $search)
                        ->orWhere('device_id', 'like', "%{$search}%")
                        ->orWhere('view_source', 'like', "%{$search}%")
                        ->orWhereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"))
                        ->orWhereHas('viewer', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('view_source'), fn ($query) => $query->where('view_source', $request->string('view_source')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.engagement.PostViewsPage', compact('views'));
    }

    public static function tabCounts(): array
    {
        return [
            'post_likes' => PostLike::count(),
            'comment_likes' => CommentLike::count(),
            'reply_likes' => ReplyLike::count(),
            'shares' => PostShare::count(),
            'saved' => SavedPost::count(),
            'views' => PostView::count(),
        ];
    }

    private function applyPostUserSearch($query, string $search): void
    {
        $query->where(function ($query) use ($search) {
            $query->where('id', $search)
                ->orWhereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"))
                ->orWhereHas('user', fn ($query) => $query
                    ->where('username', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%"));
        });
    }
}
