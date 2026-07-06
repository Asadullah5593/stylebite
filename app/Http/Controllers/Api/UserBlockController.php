<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserFollow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UserBlockController extends Controller
{
    public function toggle(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();

        $target = User::query()
            ->with('profile')
            ->where('username', $username)
            ->firstOrFail();

        if ($viewer->id === $target->id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You cannot block yourself.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($viewer, $target, $validated) {
            $existingBlock = UserBlock::query()
                ->where('blocker_user_id', $viewer->id)
                ->where('blocked_user_id', $target->id)
                ->first();

            if ($existingBlock) {
                $existingBlock->delete();

                return [
                    'is_blocked' => false,
                    'message' => 'User unblocked successfully.',
                ];
            }

            UserBlock::query()->create([
                'blocker_user_id' => $viewer->id,
                'blocked_user_id' => $target->id,
                'reason' => $validated['reason'] ?? null,
                'created_at' => now(),
            ]);

            // Remove follow relationships in both directions on block.
            UserFollow::query()
                ->where('follower_user_id', $viewer->id)
                ->where('following_user_id', $target->id)
                ->whereNull('deleted_at')
                ->delete();

            UserFollow::query()
                ->where('follower_user_id', $target->id)
                ->where('following_user_id', $viewer->id)
                ->whereNull('deleted_at')
                ->delete();

            return [
                'is_blocked' => true,
                'message' => 'User blocked successfully.',
            ];
        });

        return response()->json([
            'status_code' => 1,
            'message' => $result['message'],
            'relationship' => [
                'user' => [
                    'id' => $target->id,
                    'username' => $target->username,
                    'display_name' => $target->profile?->display_name ?? $target->full_name,
                    'avatar_url' => stylebite_asset_url($target->avatar_url),
                ],
                'is_blocked' => $result['is_blocked'],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'skip' => ['nullable', 'integer', 'min:0'],
        ]);

        $viewer = $request->user();
        $perPage = 10;
        $skip = (int) ($validated['skip'] ?? $validated['offset'] ?? 0);
        $page = (int) ($validated['page'] ?? (intdiv($skip, $perPage) + 1));

        $baseQuery = UserBlock::query()
            ->where('blocker_user_id', $viewer->id);

        $total = (clone $baseQuery)->count();

        $blocks = $baseQuery
            ->with(['blocked.profile'])
            ->latest('created_at')
            ->skip($skip)
            ->take($perPage)
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Blocked users fetched successfully.',
            'blocked_users' => $blocks->map(function (UserBlock $block): array {
                $blocked = $block->blocked;

                return [
                    'block_id' => $block->id,
                    'blocked_at' => optional($block->created_at)?->toIso8601String(),
                    'reason' => $block->reason,
                    'user' => [
                        'id' => $blocked?->id,
                        'username' => $blocked?->username,
                        'display_name' => $blocked?->profile?->display_name ?? $blocked?->full_name,
                        'avatar_url' => stylebite_asset_url($blocked?->avatar_url),
                    ],
                ];
            }),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                'offset' => $skip,
                'skip' => $skip,
            ],
        ]);
    }
}
