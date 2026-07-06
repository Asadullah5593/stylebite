<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaUpload;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function uploads(Request $request): View
    {
        $uploads = MediaUpload::query()
            ->with('user:id,username,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('original_file_name', 'like', "%{$search}%")
                        ->orWhere('file_url', 'like', "%{$search}%")
                        ->orWhere('mime_type', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('media_type'), fn ($query) => $query->where('media_type', $request->string('media_type')))
            ->when($request->filled('upload_status'), fn ($query) => $query->where('upload_status', $request->string('upload_status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.media.UploadsPage', compact('uploads'));
    }

    public function tags(Request $request): View
    {
        $tags = Tag::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('normalized_name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.media.TagsPage', compact('tags'));
    }

    public static function tabCounts(): array
    {
        return [
            'uploads' => MediaUpload::count(),
            'tags' => Tag::count(),
        ];
    }
}
