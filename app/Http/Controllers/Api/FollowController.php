<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class FollowController extends Controller
{
    public function follow(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();
        $target = $this->findAccessibleTargetUser($viewer->id, $username);

        if ($viewer->id === $target->id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You cannot follow yourself.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $created = DB::transaction(function () use ($viewer, $target) {
            $follow = UserFollow::query()
                ->withTrashed()
                ->where('follower_user_id', $viewer->id)
                ->where('following_user_id', $target->id)
                ->first();

            if ($follow && $follow->deleted_at === null && $follow->status === 'accepted') {
                return false;
            }

            if ($follow) {
                $follow->deleted_at = null;
                $follow->status = 'accepted';
                $follow->save();
            } else {
                UserFollow::query()->create([
                    'follower_user_id' => $viewer->id,
                    'following_user_id' => $target->id,
                    'status' => 'accepted',
                ]);
            }

            $this->syncFollowCounts($viewer->id, $target->id);

            return true;
        });

        $target->load('profile');

        return response()->json([
            'status_code' => 1,
            'message' => $created ? 'User followed successfully.' : 'You are already following this user.',
            'relationship' => $this->relationshipPayload($viewer->id, $target),
        ]);
    }

    public function unfollow(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();
        $target = $this->findAccessibleTargetUser($viewer->id, $username);

        if ($viewer->id === $target->id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You cannot unfollow yourself.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $removed = DB::transaction(function () use ($viewer, $target) {
            $follow = UserFollow::query()
                ->where('follower_user_id', $viewer->id)
                ->where('following_user_id', $target->id)
                ->whereNull('deleted_at')
                ->first();

            if (! $follow) {
                return false;
            }

            $follow->delete();
            $this->syncFollowCounts($viewer->id, $target->id);

            return true;
        });

        $target->load('profile');

        return response()->json([
            'status_code' => 1,
            'message' => $removed ? 'User unfollowed successfully.' : 'You are not following this user.',
            'relationship' => $this->relationshipPayload($viewer->id, $target),
        ]);
    }

    public function followers(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();
        $target = $this->findAccessibleTargetUser($viewer->id, $username);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 20;

        $paginator = User::query()
            ->with('profile')
            ->whereIn('id', UserFollow::query()
                ->select('follower_user_id')
                ->where('following_user_id', $target->id)
                ->where('status', 'accepted')
                ->whereNull('deleted_at'))
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status_code' => 1,
            'message' => 'Followers fetched successfully.',
            'profile_user' => [
                'id' => $target->id,
                'username' => $target->username,
                'display_name' => $target->profile?->display_name ?? $target->full_name,
            ],
            'followers' => $this->connectionCollectionPayload($viewer->id, $paginator),
            'pagination' => $this->paginationPayload($paginator),
        ]);
    }

    public function following(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();
        $target = $this->findAccessibleTargetUser($viewer->id, $username);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 20;

        $paginator = User::query()
            ->with('profile')
            ->whereIn('id', UserFollow::query()
                ->select('following_user_id')
                ->where('follower_user_id', $target->id)
                ->where('status', 'accepted')
                ->whereNull('deleted_at'))
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status_code' => 1,
            'message' => 'Following fetched successfully.',
            'profile_user' => [
                'id' => $target->id,
                'username' => $target->username,
                'display_name' => $target->profile?->display_name ?? $target->full_name,
            ],
            'following' => $this->connectionCollectionPayload($viewer->id, $paginator),
            'pagination' => $this->paginationPayload($paginator),
        ]);
    }

    private function findAccessibleTargetUser(int $viewerUserId, string $username): User
    {
        $target = User::query()
            ->with('profile')
            ->where('username', $username)
            ->firstOrFail();

        $viewerBlockedTarget = $target->blockedByEntries()
            ->where('blocker_user_id', $viewerUserId)
            ->exists();

        $targetBlockedViewer = $target->blockedUsersEntries()
            ->where('blocked_user_id', $viewerUserId)
            ->exists();

        if ($viewerBlockedTarget || $targetBlockedViewer) {
            abort(response()->json([
                'status_code' => 0,
                'message' => 'User not found or not accessible.',
            ], Response::HTTP_NOT_FOUND));
        }

        return $target;
    }

    private function syncFollowCounts(int $viewerUserId, int $targetUserId): void
    {
        $viewerFollowingCount = UserFollow::query()
            ->where('follower_user_id', $viewerUserId)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->count();

        $targetFollowerCount = UserFollow::query()
            ->where('following_user_id', $targetUserId)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->count();

        Profile::query()->firstOrCreate(['user_id' => $viewerUserId])->update([
            'following_count' => $viewerFollowingCount,
        ]);

        Profile::query()->firstOrCreate(['user_id' => $targetUserId])->update([
            'follower_count' => $targetFollowerCount,
        ]);
    }

    private function relationshipPayload(int $viewerUserId, User $target): array
    {
        $isFollowing = UserFollow::query()
            ->where('follower_user_id', $viewerUserId)
            ->where('following_user_id', $target->id)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->exists();

        $followsYou = UserFollow::query()
            ->where('follower_user_id', $target->id)
            ->where('following_user_id', $viewerUserId)
            ->where('status', 'accepted')
            ->whereNull('deleted_at')
            ->exists();

        $target->loadMissing('profile');

        return [
            'user' => [
                'id' => $target->id,
                'username' => $target->username,
                'display_name' => $target->profile?->display_name ?? $target->full_name,
                'avatar_url' => stylebite_asset_url($target->avatar_url),
            ],
            'is_following' => $isFollowing,
            'follows_you' => $followsYou,
            'is_mutual_follow' => $isFollowing && $followsYou,
            'follower_count' => (int) ($target->profile?->follower_count ?? 0),
            'following_count' => (int) ($target->profile?->following_count ?? 0),
        ];
    }

    private function connectionCollectionPayload(int $viewerUserId, LengthAwarePaginator $paginator): Collection
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
            $isSelf = $viewerUserId === (int) $user->id;
            $isFollowing = $viewerFollowingIds->contains($user->id);
            $followsYou = $viewerFollowerIds->contains($user->id);

            return [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->profile?->display_name ?? $user->full_name,
                'full_name' => $user->full_name,
                'avatar_url' => stylebite_asset_url($user->avatar_url),
                'bio' => $user->profile?->bio,
                'is_private' => (bool) ($user->profile?->is_private ?? false),
                'is_self' => $isSelf,
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
