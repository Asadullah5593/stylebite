<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $viewerUserId = $request->user()->id;
        $search = trim((string) ($validated['query'] ?? ''));
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);

        $query = User::query()
            ->with('profile')
            ->whereHas('profile', function (Builder $query) {
                $query
                    ->where('visibility', 'public')
                    ->where('is_private', false);
            })
            ->whereDoesntHave('blockedByEntries', function (Builder $query) use ($viewerUserId) {
                $query->where('blocker_user_id', $viewerUserId);
            })
            ->whereDoesntHave('blockedUsersEntries', function (Builder $query) use ($viewerUserId) {
                $query->where('blocked_user_id', $viewerUserId);
            })
            ->orderBy('username');

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where('username', 'like', '%'.$search.'%')
                    ->orWhere('full_name', 'like', '%'.$search.'%')
                    ->orWhereHas('profile', function (Builder $profileQuery) use ($search) {
                        $profileQuery
                            ->where('display_name', 'like', '%'.$search.'%')
                            ->orWhere('bio', 'like', '%'.$search.'%')
                            ->orWhere('city', 'like', '%'.$search.'%')
                            ->orWhere('country', 'like', '%'.$search.'%');
                    });
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status_code' => 1,
            'message' => 'Users fetched successfully.',
            'users' => $this->userCollectionPayload($viewerUserId, $paginator),
            'pagination' => $this->paginationPayload($paginator),
            'search_query' => $search,
        ]);
    }

    private function userCollectionPayload(int $viewerUserId, LengthAwarePaginator $paginator): Collection
    {
        $users = $paginator->getCollection();
        $userIds = $users->pluck('id')->all();

        $viewerFollowingIds = UserFollow::query()
            ->where('follower_user_id', $viewerUserId)
            ->whereIn('following_user_id', $userIds)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->pluck('following_user_id');

        $viewerFollowerIds = UserFollow::query()
            ->where('following_user_id', $viewerUserId)
            ->whereIn('follower_user_id', $userIds)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->pluck('follower_user_id');

        return $users->map(function (User $user) use ($viewerUserId, $viewerFollowingIds, $viewerFollowerIds) {
            $isFollowing = $viewerFollowingIds->contains($user->id);
            $followsYou = $viewerFollowerIds->contains($user->id);

            return [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->profile?->display_name ?? $user->full_name,
                'full_name' => $user->full_name,
                'avatar_url' => stylebite_asset_url($user->avatar_url),
                'bio' => $user->profile?->bio,
                'city' => $user->profile?->city,
                'country' => $user->profile?->country,
                'is_private' => (bool) ($user->profile?->is_private ?? false),
                'is_self' => $viewerUserId === (int) $user->id,
                'is_following' => $isFollowing,
                'follows_you' => $followsYou,
                'is_mutual_follow' => $isFollowing && $followsYou,
                'follower_count' => (int) ($user->profile?->follower_count ?? 0),
                'following_count' => (int) ($user->profile?->following_count ?? 0),
            ];
        })->values();
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}
