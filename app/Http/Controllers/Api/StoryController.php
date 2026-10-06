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

        $authorIds = $followingIds
            ->push($viewer->id)
            ->unique()
            ->values();

        $posts = Post::query()
            ->with([
                'user',
                'media' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->whereIn('user_id', $authorIds)
            ->where('is_story', true)
            ->where('status', 'published')
            ->where('moderation_status', 'clean')
            ->where('is_blocked', false)
            ->where('expires_at', '>', now())
            ->whereDoesntHave('user.blockedUsersEntries', fn ($query) => $query->where('blocked_user_id', $viewer->id))
            ->whereDoesntHave('user.blockedByEntries', fn ($query) => $query->where('blocker_user_id', $viewer->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
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
            $groups[$key]['items'][] = [
                'id' => $post->id,
                'media_url' => stylebite_asset_url($media?->file_url),
                'media_type' => $media?->media_type ?? 'image',
                'caption' => $post->caption,
                'posted_at' => $post->posted_at?->toISOString(),
                'expires_at' => $post->expires_at?->toISOString(),
            ];
        }

        $mine = [];
        $others = [];
        foreach ($groups as $group) {
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
