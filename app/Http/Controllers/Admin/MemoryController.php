<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryMedia;
use App\Models\SavedMemory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemoryController extends Controller
{
    public function index(Request $request): View
    {
        $memories = Memory::query()
            ->with('user:id,username,full_name')
            ->withCount('media')
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

        return view('admin.memories.MemoriesPage', compact('memories'));
    }

    public function media(Request $request): View
    {
        $memoryMedia = MemoryMedia::query()
            ->with(['memory:id,title', 'upload:id,file_url,thumbnail_url'])
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
            'memories' => Memory::count(),
            'memory_media' => MemoryMedia::count(),
            'memory_comments' => MemoryComment::count(),
            'saved_memories' => SavedMemory::count(),
        ];
    }
}
