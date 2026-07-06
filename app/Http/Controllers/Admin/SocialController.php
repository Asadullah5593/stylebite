<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\UserBlock;
use App\Models\UserFollow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialController extends Controller
{
    public function follows(Request $request): View
    {
        $follows = UserFollow::query()
            ->with([
                'follower' => fn ($query) => $query->withTrashed()->select('id', 'username', 'full_name', 'avatar_url', 'status'),
                'following' => fn ($query) => $query->withTrashed()->select('id', 'username', 'full_name', 'avatar_url', 'status'),
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('id', $search)
                        ->orWhereHas('follower', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('following', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('record_state'), function ($query) use ($request) {
                $state = $request->string('record_state')->toString();

                if ($state === 'deleted') {
                    $query->onlyTrashed();
                } elseif ($state === 'active') {
                    $query->whereNull('deleted_at');
                }
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.social.FollowsPage', compact('follows'));
    }

    public function blocks(Request $request): View
    {
        $blocks = UserBlock::query()
            ->with([
                'blocker' => fn ($query) => $query->withTrashed()->select('id', 'username', 'full_name', 'avatar_url', 'status'),
                'blocked' => fn ($query) => $query->withTrashed()->select('id', 'username', 'full_name', 'avatar_url', 'status'),
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('id', $search)
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('blocker', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('blocked', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.social.BlocksPage', compact('blocks'));
    }

    public function deleteFollow(UserFollow $follow): RedirectResponse
    {
        $this->logActivity('social_follow_deleted', 'user_follow', $follow->id, [
            'follower_user_id' => $follow->follower_user_id,
            'following_user_id' => $follow->following_user_id,
            'status' => $follow->status,
        ]);

        $follow->delete();

        return back()->with('status', 'Follow record removed successfully.');
    }

    public function deleteBlock(UserBlock $block): RedirectResponse
    {
        $this->logActivity('social_block_deleted', 'user_block', $block->id, [
            'blocker_user_id' => $block->blocker_user_id,
            'blocked_user_id' => $block->blocked_user_id,
            'reason' => $block->reason,
        ]);

        $block->delete();

        return back()->with('status', 'Block record removed successfully.');
    }

    public static function tabCounts(): array
    {
        return [
            'follows' => UserFollow::withTrashed()->count(),
            'blocks' => UserBlock::count(),
        ];
    }

    private function logActivity(string $eventName, ?string $entityType, ?int $entityId, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
