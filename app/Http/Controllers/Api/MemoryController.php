<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaUpload;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryMedia;
use App\Models\MemoryRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MemoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'skip' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,archived,deleted'],
            'visibility' => ['nullable', 'string', 'in:public,private,followers_only'],
            'search' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $skip = (int) ($validated['skip'] ?? $validated['offset'] ?? 0);
        $page = (int) ($validated['page'] ?? (intdiv($skip, $perPage) + 1));

        $baseQuery = Memory::query()
            ->with(['media'])
            ->where('user_id', $user->id)
            ->when(isset($validated['status']), fn (Builder $query) => $query->where('status', $validated['status']))
            ->when(isset($validated['visibility']), fn (Builder $query) => $query->where('visibility', $validated['visibility']))
            ->when(isset($validated['search']), function (Builder $query) use ($validated) {
                $search = trim((string) $validated['search']);

                if ($search === '') {
                    return;
                }

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('short_title', 'like', '%'.$search.'%')
                        ->orWhere('short_description', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('location_name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhere('country', 'like', '%'.$search.'%');
                });
            });

        $total = (clone $baseQuery)->count();
        $memories = $baseQuery
            ->latest('memory_date')
            ->latest('id')
            ->skip($skip)
            ->take($perPage)
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Memories fetched successfully.',
            'memories' => $memories->map(fn (Memory $memory) => $this->memoryPayload($memory)),
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

    public function show(Request $request, int $memoryId): JsonResponse
    {
        $memory = $this->findOwnedMemory($request, $memoryId, true);

        return response()->json([
            'status_code' => 1,
            'message' => 'Memory fetched successfully.',
            'memory' => $this->memoryPayload($memory, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateMemory($request);
        $user = $request->user();

        $memory = DB::transaction(function () use ($validated, $request, $user) {
            $memory = Memory::create([
                'user_id' => $user->id,
                'title' => $validated['title'],
                'short_title' => $validated['short_title'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'note' => $validated['note'] ?? null,
                'memory_date' => $validated['memory_date'],
                'location_name' => $validated['location'] ?? null,
                'location_lat' => $validated['lat'] ?? null,
                'location_lng' => $validated['lng'] ?? null,
                'city' => $validated['city'] ?? null,
                'country' => $validated['country'] ?? null,
                'rating' => $validated['rating'] ?? null,
                'rating_count' => isset($validated['rating']) ? 1 : 0,
                'is_favorite' => (bool) ($validated['is_favorite'] ?? false),
                'visibility' => $validated['visibility'] ?? 'public',
                'status' => $validated['status'] ?? 'active',
                'like_count' => 0,
                'comment_count' => 0,
                'save_count' => 0,
            ]);

            $this->syncMemoryMedia($memory, $request);
            $this->syncInitialEngagement($memory, $user->id, $validated);

            return $memory->fresh(['media', 'likes', 'ratings', 'comments.user']);
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Memory created successfully.',
            'memory' => $this->memoryPayload($memory, true),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $memoryId): JsonResponse
    {
        $memory = $this->findOwnedMemory($request, $memoryId);
        $validated = $this->validateMemory($request, true);

        $memory = DB::transaction(function () use ($memory, $validated, $request) {
            $memory->fill([
                'title' => $validated['title'] ?? $memory->title,
                'short_title' => array_key_exists('short_title', $validated) ? $validated['short_title'] : $memory->short_title,
                'short_description' => array_key_exists('short_description', $validated) ? $validated['short_description'] : $memory->short_description,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $memory->description,
                'note' => array_key_exists('note', $validated) ? $validated['note'] : $memory->note,
                'memory_date' => $validated['memory_date'] ?? $memory->memory_date,
                'location_name' => array_key_exists('location', $validated) ? $validated['location'] : $memory->location_name,
                'location_lat' => array_key_exists('lat', $validated) ? $validated['lat'] : $memory->location_lat,
                'location_lng' => array_key_exists('lng', $validated) ? $validated['lng'] : $memory->location_lng,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $memory->city,
                'country' => array_key_exists('country', $validated) ? $validated['country'] : $memory->country,
                'rating' => array_key_exists('rating', $validated) ? $validated['rating'] : $memory->rating,
                'rating_count' => array_key_exists('rating', $validated)
                    ? ($validated['rating'] === null ? 0 : max(1, (int) $memory->rating_count))
                    : $memory->rating_count,
                'is_favorite' => array_key_exists('is_favorite', $validated) ? (bool) $validated['is_favorite'] : $memory->is_favorite,
                'visibility' => $validated['visibility'] ?? $memory->visibility,
                'status' => $validated['status'] ?? $memory->status,
            ]);

            $memory->save();

            $this->syncMemoryMedia($memory, $request, true);
            $this->syncInitialEngagement($memory, $request->user()->id, $validated, true);

            return $memory->fresh(['media', 'likes', 'ratings', 'comments.user']);
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Memory updated successfully.',
            'memory' => $this->memoryPayload($memory, true),
        ]);
    }

    public function toggleFavorite(Request $request, int $memoryId): JsonResponse
    {
        $memory = $this->findOwnedMemory($request, $memoryId);
        $memory->is_favorite = ! $memory->is_favorite;
        $memory->save();

        return response()->json([
            'status_code' => 1,
            'message' => $memory->is_favorite ? 'Memory marked as favorite.' : 'Memory removed from favorites.',
            'is_favorite' => $memory->is_favorite,
        ]);
    }

    public function destroyMedia(Request $request, int $memoryId, int $mediaId): JsonResponse
    {
        $memory = $this->findOwnedMemory($request, $memoryId);

        $media = $memory->media()->find($mediaId);

        if (! $media) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Media not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $media->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Media deleted successfully.',
        ]);
    }

    public function destroy(Request $request, int $memoryId): JsonResponse
    {
        $memory = $this->findOwnedMemory($request, $memoryId);
        $memory->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Memory deleted successfully.',
        ]);
    }

    private function validateMemory(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? ['sometimes'] : ['required'];

        return $request->validate([
            'title' => array_merge($required, ['string', 'max:191']),
            'short_title' => ['nullable', 'string', 'max:120'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
            'memory_date' => array_merge($required, ['date']),
            'location' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'is_favorite' => ['nullable', 'boolean'],
            'visibility' => ['nullable', 'string', 'in:public,private,followers_only'],
            'status' => ['nullable', 'string', 'in:active,archived,deleted'],
            'images' => $isUpdate ? ['nullable', 'array', 'min:1'] : ['required', 'array', 'min:1'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp'],
            'retain_media_ids' => ['nullable', 'array'],
            'retain_media_ids.*' => ['integer'],
            'comments' => ['nullable', 'array'],
            'comments.*' => ['string'],
        ]);
    }

    private function syncMemoryMedia(Memory $memory, Request $request, bool $isUpdate = false): void
    {
        if ($isUpdate && $request->has('retain_media_ids')) {
            $retainMediaIds = collect($request->input('retain_media_ids', []))
                ->map(fn (mixed $id) => (int) $id)
                ->all();

            $memory->media()
                ->when($retainMediaIds !== [], fn (Builder $query) => $query->whereNotIn('id', $retainMediaIds))
                ->when($retainMediaIds === [], fn (Builder $query) => $query)
                ->delete();
        }

        if (! $request->hasFile('images')) {
            return;
        }

        $startingSort = (int) $memory->media()->max('sort_order') + 1;

        foreach ($request->file('images', []) as $index => $file) {
            $uploadedFile = stylebite_upload_file($file, 'memories/'.$memory->user_id);

            $upload = MediaUpload::create([
                'user_id' => $memory->user_id,
                'source' => 'gallery',
                'upload_type' => 'memory_media',
                'media_type' => 'image',
                'original_file_name' => $uploadedFile['original_file_name'],
                'file_path' => $uploadedFile['file_path'],
                'file_url' => $uploadedFile['file_url'],
                'mime_type' => $uploadedFile['mime_type'],
                'size_bytes' => $uploadedFile['size_bytes'],
                'storage_type' => 'local',
                'upload_status' => 'ready',
                'uploaded_at' => now(),
            ]);

            MemoryMedia::create([
                'memory_id' => $memory->id,
                'upload_id' => $upload->id,
                'media_type' => 'image',
                'file_path' => $uploadedFile['file_path'],
                'file_url' => $uploadedFile['file_url'],
                'mime_type' => $uploadedFile['mime_type'],
                'size_bytes' => $uploadedFile['size_bytes'],
                'storage_type' => 'local',
                'sort_order' => $startingSort + $index,
            ]);
        }
    }

    private function syncInitialEngagement(Memory $memory, int $userId, array $validated, bool $isUpdate = false): void
    {
        if (array_key_exists('rating', $validated)) {
            if ($validated['rating'] === null) {
                $memory->ratings()->delete();
            } else {
                MemoryRating::query()->updateOrCreate(
                    [
                        'memory_id' => $memory->id,
                        'user_id' => $userId,
                    ],
                    [
                        'rating_value' => (int) round((float) $validated['rating']),
                    ]
                );
            }
        }

        if (array_key_exists('comments', $validated)) {
            if ($isUpdate) {
                $memory->comments()->delete();
            }

            $comments = collect($validated['comments'])
                ->map(fn (mixed $comment) => trim((string) $comment))
                ->filter()
                ->values();

            foreach ($comments as $comment) {
                MemoryComment::create([
                    'memory_id' => $memory->id,
                    'user_id' => $userId,
                    'body' => $comment,
                ]);
            }

            $memory->comment_count = $comments->count();
        }

        $memory->rating = $memory->ratings()->avg('rating_value');
        $memory->rating_count = $memory->ratings()->count();
        $memory->like_count = $memory->likes()->count();
        $memory->save_count = $memory->saves()->count();
        $memory->comment_count = $memory->comments()->count();
        $memory->save();
    }

    private function memoryPayload(Memory $memory, bool $includeDetails = false): array
    {
        $memory->loadMissing(['media']);

        $payload = [
            'id' => $memory->id,
            'user_id' => $memory->user_id,
            'title' => $memory->title,
            'short_title' => $memory->short_title,
            'short_description' => $memory->short_description,
            'description' => $memory->description,
            'note' => $memory->note,
            'memory_date' => optional($memory->memory_date)->toDateString(),
            'location' => $memory->location_name,
            'city' => $memory->city,
            'country' => $memory->country,
            'lat' => $memory->location_lat !== null ? (float) $memory->location_lat : null,
            'lng' => $memory->location_lng !== null ? (float) $memory->location_lng : null,
            'rating' => $memory->rating !== null ? (float) $memory->rating : null,
            'rating_count' => (int) $memory->rating_count,
            'is_favorite' => (bool) $memory->is_favorite,
            'visibility' => $memory->visibility,
            'status' => $memory->status,
            'like_count' => (int) $memory->like_count,
            'comment_count' => (int) $memory->comment_count,
            'save_count' => (int) $memory->save_count,
            'images' => $memory->media
                ->sortBy('sort_order')
                ->values()
                ->map(fn (MemoryMedia $media) => [
                    'id' => $media->id,
                    'file_url' => stylebite_asset_url($media->file_url),
                    'file_path' => $media->file_path,
                    'thumbnail_url' => stylebite_asset_url($media->thumbnail_url),
                    'mime_type' => $media->mime_type,
                    'size_bytes' => $media->size_bytes,
                    'sort_order' => $media->sort_order,
                ]),
            'created_at' => optional($memory->created_at)->toDateTimeString(),
            'updated_at' => optional($memory->updated_at)->toDateTimeString(),
        ];

        if (! $includeDetails) {
            return $payload;
        }

        $memory->loadMissing(['likes', 'ratings', 'comments.user']);

        $payload['likes'] = $memory->likes->map(fn ($like) => [
            'id' => $like->id,
            'user_id' => $like->user_id,
            'created_at' => optional($like->created_at)->toDateTimeString(),
        ]);
        $payload['ratings'] = $memory->ratings->map(fn ($rating) => [
            'id' => $rating->id,
            'user_id' => $rating->user_id,
            'rating_value' => $rating->rating_value,
            'created_at' => optional($rating->created_at)->toDateTimeString(),
            'updated_at' => optional($rating->updated_at)->toDateTimeString(),
        ]);
        $payload['comments'] = $memory->comments->map(fn (MemoryComment $comment) => [
            'id' => $comment->id,
            'user_id' => $comment->user_id,
            'user_name' => $comment->user?->full_name,
            'body' => $comment->body,
            'status' => $comment->status,
            'like_count' => $comment->like_count,
            'created_at' => optional($comment->created_at)->toDateTimeString(),
        ]);

        return $payload;
    }

    private function findOwnedMemory(Request $request, int $memoryId, bool $includeDetails = false): Memory
    {
        $query = Memory::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($memoryId);

        if ($includeDetails) {
            $query->with(['media', 'likes', 'ratings', 'comments.user']);
        } else {
            $query->with('media');
        }

        $memory = $query->first();

        if ($memory) {
            return $memory;
        }

        abort(response()->json([
            'status_code' => 0,
            'message' => 'Memory not found.',
        ], Response::HTTP_NOT_FOUND));
    }
}
