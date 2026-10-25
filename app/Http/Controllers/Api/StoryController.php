<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        $followingIds = DB::table('user_follows')
            ->where('follower_user_id', $viewer->id)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->pluck('following_user_id');

        $posts = Post::query()
            ->with([
                'user',
                'media' => fn ($query) => $query->orderBy('sort_order'),
                'tags',
            ])
            ->where('status', 'published')
            ->where('moderation_status', 'clean')
            ->where('is_blocked', false)
            ->where('is_flash', false)
            ->where(function ($query) {
                $query->where('is_story', true)
                    ->orWhere('caption', 'like', '%#sbstory%')
                    ->orWhereHas('tags', fn ($tags) => $tags->whereRaw('LOWER(name) = ?', ['sbstory']));
            })
            ->where(function ($query) {
                $cutoff = now()->subDay();
                $query->where('expires_at', '>', now())
                    ->orWhere(function ($open) use ($cutoff) {
                        $open->whereNull('expires_at')
                            ->where(function ($recent) use ($cutoff) {
                                $recent->where('published_at', '>=', $cutoff)
                                    ->orWhere('posted_at', '>=', $cutoff)
                                    ->orWhere('created_at', '>=', $cutoff);
                            });
                    });
            })
            ->where(function ($query) use ($viewer, $followingIds) {
                $query->where('user_id', $viewer->id)
                    ->orWhere('visibility', 'public')
                    ->orWhere(function ($followers) use ($followingIds) {
                        $followers->where('visibility', 'followers_only');
                        if ($followingIds->isEmpty()) {
                            $followers->whereRaw('0 = 1');
                        } else {
                            $followers->whereIn('user_id', $followingIds->all());
                        }
                    });
            })
            ->whereDoesntHave('user.blockedUsersEntries', fn ($query) => $query->where('blocked_user_id', $viewer->id))
            ->whereDoesntHave('user.blockedByEntries', fn ($query) => $query->where('blocker_user_id', $viewer->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $groups = [];
        foreach ($posts as $post) {
            $author = $post->user;
            if ($author === null) {
                continue;
            }

            $key = (int) $author->id;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'user_id' => $author->id,
                    'username' => $author->username,
                    'avatar_url' => stylebite_asset_url($author->avatar_url),
                    'is_mine' => $key === (int) $viewer->id,
                    'items' => [],
                ];
            }

            $media = $post->media->sortBy('sort_order')->first();
            $mediaUrl = stylebite_asset_url($media?->optimized_url ?? $media?->file_url);
            if ($mediaUrl === null || $mediaUrl === '') {
                // Client drops empty media_url — skip rather than emit a dead ring item.
                continue;
            }
            $groups[$key]['items'][] = [
                'id' => $post->id,
                'media_url' => $mediaUrl,
                'poster_url' => stylebite_asset_url($media?->thumbnail_url),
                'media_type' => $media?->media_type ?? 'image',
                'caption' => $post->caption,
                'post_type' => $post->post_type,
                'feed_type' => $post->feed_type,
                'posted_at' => $post->posted_at?->toISOString(),
                'expires_at' => $post->expires_at?->toISOString(),
            ];
        }

        $mine = [];
        $others = [];
        foreach ($groups as $group) {
            if (($group['items'] ?? []) === []) {
                continue;
            }
            if ($group['is_mine']) {
                $mine[] = $group;
            } else {
                $others[] = $group;
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Stories fetched successfully.',
            'data' => array_merge($mine, $others),
        ]);
    }
}
