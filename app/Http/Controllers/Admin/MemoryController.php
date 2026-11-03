<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryMedia;
use App\Models\SavedMemory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemoryController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->integer('user_id') ?: null;
        $filterUser = $userId
            ? User::query()->select('id', 'username', 'full_name')->find($userId)
            : null;

        $memories = Memory::query()
            ->with([
                'user:id,username,full_name',
                // Avoid limit() on eager loads (Eloquent can share one limit across parents).
                'media' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->withCount('media')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('visibility'), fn ($query) => $query->where('visibility', $request->string('visibility')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.memories.MemoriesPage', compact('memories', 'filterUser'));
    }

    public function users(Request $request): View
    {
        $users = User::query()
            ->select('id', 'username', 'full_name', 'avatar_url')
            ->whereHas('memories')
            ->withCount('memories')
            ->withMax('memories as latest_memory_at', 'memory_date')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('memories_count')
            ->paginate(12)
            ->withQueryString();

        return view('admin.memories.MemoryUsersPage', compact('users'));
    }

    public function show(Memory $memory): View
    {
        $memory->load([
            'user:id,username,full_name,email,avatar_url',
            'media' => fn ($query) => $query->orderBy('sort_order'),
            'comments' => fn ($query) => $query->latest()->limit(20)->with('user:id,username,full_name'),
        ]);

        return view('admin.memories.ShowMemoryPage', compact('memory'));
    }

    public function media(Request $request): View
    {
        $memoryMedia = MemoryMedia::query()
            ->with([
                'memory:id,title,user_id',
                'memory.user:id,username,full_name',
                'upload:id,file_url,thumbnail_url',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('file_url', 'like', "%{$search}%")
                        ->orWhere('media_type', 'like', "%{$search}%")
                        ->orWhereHas('memory', fn ($query) => $query->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('media_type'), fn ($query) => $query->where('media_type', $request->string('media_type')))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.memories.MemoryMediaPage', compact('memoryMedia'));
    }

    public function saved(Request $request): View
    {
        $savedMemories = SavedMemory::query()
            ->with(['user:id,username,full_name', 'memory:id,title'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->whereHas('user', fn ($query) => $query
                    ->where('username', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('memory', fn ($query) => $query->where('title', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.memories.SavedMemoriesPage', compact('savedMemories'));
    }

    public function comments(Request $request): View
    {
        $memoryComments = MemoryComment::query()
            ->with([
                'memory:id,title,user_id',
                'memory.user:id,username,full_name',
                'user:id,username,full_name',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('body', 'like', "%{$search}%")
                        ->orWhereHas('memory', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('reported'), fn ($query) => $query->where('is_reported', $request->boolean('reported')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.memories.MemoryCommentsPage', compact('memoryComments'));
    }

    public function updateComment(Request $request, MemoryComment $memoryComment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,hidden,deleted,blocked'],
        ]);

        $memoryComment->update([
            'status' => $data['status'],
            'is_blocked' => $data['status'] === 'blocked',
        ]);

        return back()->with('status', "Memory comment #{$memoryComment->id} updated successfully.");
    }

    public static function tabCounts(): array
    {
        return [
            'memory_users' => User::query()->whereHas('memories')->count(),
            'memories' => Memory::count(),
            'memory_media' => MemoryMedia::count(),
            'memory_comments' => MemoryComment::count(),
            'saved_memories' => SavedMemory::count(),
        ];
    }
}
