<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\OptimizePostMedia;
use App\Models\MediaUpload;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTag;
use App\Models\Tag;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('post_type', description: 'One of: outfit, food (legacy: 1 = outfit, 2 = food).', required: true, type: 'string', example: 'outfit')]
    #[BodyParameter('file', description: 'Media file (jpg, jpeg, png, webp, mp4, mov, avi, mkv, webm).', required: true, type: 'string', format: 'binary')]
    #[BodyParameter('caption', type: 'string', example: 'My summer look.')]
    #[BodyParameter('tags', description: 'Comma-separated tag names.', type: 'string', example: 'fashion,summer')]
    #[BodyParameter('location', type: 'string', example: 'Lahore, Pakistan')]
    #[BodyParameter('visibility', description: 'One of: public, private, followers_only.', type: 'string', example: 'public')]
    #[BodyParameter('lat', description: 'Required when lng is provided.', type: 'number', example: 31.5204)]
    #[BodyParameter('lng', description: 'Required when lat is provided.', type: 'number', example: 74.3587)]
    #[BodyParameter('rating_enabled', type: 'boolean', example: true)]
    #[BodyParameter('dish_name', description: 'Required when post_type is food.', type: 'string', example: 'Chicken Karahi')]
    #[BodyParameter('restaurant', description: 'Required when post_type is food.', type: 'string', example: 'Monal')]
    #[BodyParameter('food_rating', description: '1 to 5. Required when post_type is food.', type: 'integer', example: 5)]
    #[BodyParameter('service_rating', description: '1 to 5. Required when post_type is food.', type: 'integer', example: 4)]
    #[BodyParameter('staff_rating', description: '1 to 5. Required when post_type is food.', type: 'integer', example: 4)]
    #[BodyParameter('ambience_rating', description: '1 to 5. Required when post_type is food.', type: 'integer', example: 5)]
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePost($request);

        $normalizedPostType = $this->normalizePostType($validated['post_type']);
        $this->validatePostTypeFields($request, $normalizedPostType);
        $locationDetails = $this->resolveLocationDetails($validated);

        $user = $request->user();
        $file = $request->file('file');
        $mediaType = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
        $uploadedFile = stylebite_upload_file($file, 'posts/'.$user->id);

        $post = DB::transaction(function () use ($validated, $normalizedPostType, $user, $mediaType, $uploadedFile, $locationDetails) {
            $post = Post::create([
                'user_id' => $user->id,
                'post_type' => $normalizedPostType,
                'content_type' => $normalizedPostType === 'food' ? 'food' : 'fashion',
                'media_kind' => $mediaType,
                'feed_type' => $normalizedPostType === 'food' ? 'bite' : 'style',
                'caption' => $validated['caption'] ?? null,
                'location_name' => $validated['location'] ?? $locationDetails['location_name'],
                'location_lat' => $locationDetails['lat'],
                'location_lng' => $locationDetails['lng'],
                'city' => $locationDetails['city'],
                'country' => $locationDetails['country'],
                'dish_name' => $normalizedPostType === 'food' ? ($validated['dish_name'] ?? null) : null,
                'restaurant' => $normalizedPostType === 'food' ? ($validated['restaurant'] ?? null) : null,
                'visibility' => $validated['visibility'] ?? 'public',
                'status' => 'published',
                'rating_enabled' => $normalizedPostType === 'food'
                    ? true
                    : (bool) ($validated['rating_enabled'] ?? false),
                'food_rating' => $normalizedPostType === 'food' ? ($validated['food_rating'] ?? null) : null,
                'service_rating' => $normalizedPostType === 'food' ? ($validated['service_rating'] ?? null) : null,
                'staff_rating' => $normalizedPostType === 'food' ? ($validated['staff_rating'] ?? null) : null,
                'ambience_rating' => $normalizedPostType === 'food' ? ($validated['ambience_rating'] ?? null) : null,
                'posted_at' => now(),
                'published_at' => now(),
            ]);

            $upload = MediaUpload::create([
                'user_id' => $user->id,
                'source' => 'gallery',
                'upload_type' => 'post_media',
                'media_type' => $mediaType,
                'original_file_name' => $uploadedFile['original_file_name'],
                'file_path' => $uploadedFile['file_path'],
                'file_url' => $uploadedFile['file_url'],
                'mime_type' => $uploadedFile['mime_type'],
                'size_bytes' => $uploadedFile['size_bytes'],
                'storage_type' => 'local',
                'upload_status' => 'ready',
                'uploaded_at' => now(),
            ]);

            PostMedia::create([
                'post_id' => $post->id,
                'upload_id' => $upload->id,
                'media_type' => $mediaType,
                'media_role' => 'original',
                'file_path' => $uploadedFile['file_path'],
                'file_url' => $uploadedFile['file_url'],
                'mime_type' => $uploadedFile['mime_type'],
                'size_bytes' => $uploadedFile['size_bytes'],
                'storage_type' => 'local',
                'sort_order' => 0,
                'processing_status' => 'pending',
            ]);

            $tagIds = $this->syncTags($validated['tags'] ?? null);

            $this->attachTagsToPost($post, $tagIds);

            return $post->fresh(['media', 'tags']);
        });

        foreach ($post->media as $media) {
            OptimizePostMedia::dispatch($media->id)->afterCommit();
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Post created successfully.',
            'post' => $this->postPayload($post),
        ], 201);
    }

    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('post_type', description: 'One of: outfit, food (legacy: 1 = outfit, 2 = food).', type: 'string', example: 'outfit')]
    #[BodyParameter('file', description: 'Replacement media file (jpg, jpeg, png, webp, mp4, mov, avi, mkv, webm).', type: 'string', format: 'binary')]
    #[BodyParameter('caption', type: 'string', example: 'My summer look.')]
    #[BodyParameter('tags', description: 'Comma-separated tag names.', type: 'string', example: 'fashion,summer')]
    #[BodyParameter('location', type: 'string', example: 'Lahore, Pakistan')]
    #[BodyParameter('visibility', description: 'One of: public, private, followers_only.', type: 'string', example: 'public')]
    #[BodyParameter('lat', description: 'Required when lng is provided.', type: 'number', example: 31.5204)]
    #[BodyParameter('lng', description: 'Required when lat is provided.', type: 'number', example: 74.3587)]
    #[BodyParameter('rating_enabled', type: 'boolean', example: true)]
    #[BodyParameter('dish_name', description: 'For food posts.', type: 'string', example: 'Chicken Karahi')]
    #[BodyParameter('restaurant', description: 'For food posts.', type: 'string', example: 'Monal')]
    #[BodyParameter('food_rating', description: '1 to 5. For food posts.', type: 'integer', example: 5)]
    #[BodyParameter('service_rating', description: '1 to 5. For food posts.', type: 'integer', example: 4)]
    #[BodyParameter('staff_rating', description: '1 to 5. For food posts.', type: 'integer', example: 4)]
    #[BodyParameter('ambience_rating', description: '1 to 5. For food posts.', type: 'integer', example: 5)]
    public function update(Request $request, int $postId): JsonResponse
    {
        $post = $this->findOwnedPost($request, $postId);
        $validated = $this->validatePost($request, true);
        $normalizedPostType = $this->normalizePostType($validated['post_type'] ?? $post->post_type);
        $this->validatePostTypeFields($request, $normalizedPostType, true);
        $locationDetails = $this->resolveLocationDetails($validated);

        $post = DB::transaction(function () use ($post, $validated, $normalizedPostType, $request, $locationDetails) {
            $post->forceFill([
                'post_type' => $normalizedPostType,
                'content_type' => $normalizedPostType === 'food' ? 'food' : 'fashion',
                'feed_type' => $normalizedPostType === 'food' ? 'bite' : 'style',
                'caption' => array_key_exists('caption', $validated) ? $validated['caption'] : $post->caption,
                'location_name' => array_key_exists('location', $validated)
                    ? ($validated['location'] ?? $locationDetails['location_name'])
                    : $post->location_name,
                'location_lat' => array_key_exists('lat', $validated) ? $locationDetails['lat'] : $post->location_lat,
                'location_lng' => array_key_exists('lng', $validated) ? $locationDetails['lng'] : $post->location_lng,
                'city' => array_key_exists('lat', $validated) || array_key_exists('lng', $validated)
                    ? $locationDetails['city']
                    : $post->city,
                'country' => array_key_exists('lat', $validated) || array_key_exists('lng', $validated)
                    ? $locationDetails['country']
                    : $post->country,
                'dish_name' => $normalizedPostType === 'food'
                    ? ($validated['dish_name'] ?? $post->dish_name)
                    : null,
                'restaurant' => $normalizedPostType === 'food'
                    ? ($validated['restaurant'] ?? $post->restaurant)
                    : null,
                'visibility' => $validated['visibility'] ?? $post->visibility,
                'rating_enabled' => $normalizedPostType === 'food'
                    ? true
                    : (array_key_exists('rating_enabled', $validated) ? (bool) $validated['rating_enabled'] : $post->rating_enabled),
                'food_rating' => $normalizedPostType === 'food'
                    ? ($validated['food_rating'] ?? $post->food_rating)
                    : null,
                'service_rating' => $normalizedPostType === 'food'
                    ? ($validated['service_rating'] ?? $post->service_rating)
                    : null,
                'staff_rating' => $normalizedPostType === 'food'
                    ? ($validated['staff_rating'] ?? $post->staff_rating)
                    : null,
                'ambience_rating' => $normalizedPostType === 'food'
                    ? ($validated['ambience_rating'] ?? $post->ambience_rating)
                    : null,
            ])->save();

            if ($request->hasFile('file')) {
                $this->replacePostMedia($post, $request->user()->id, $request->file('file'));
            }

            if (array_key_exists('tags', $validated)) {
                $tagIds = $this->syncTags($validated['tags']);
                $post->postTags()->delete();
                $this->attachTagsToPost($post, $tagIds);
            }

            return $post->fresh(['media', 'tags']);
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Post updated successfully.',
            'post' => $this->postPayload($post),
        ]);
    }

    public function destroy(Request $request, int $postId): JsonResponse
    {
        $post = $this->findOwnedPost($request, $postId);

        $post->forceFill([
            'status' => 'removed',
        ])->save();

        $post->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Post deleted successfully.',
        ]);
    }

    public function repost(Request $request, int $postId): JsonResponse
    {
        $post = $this->findOwnedPost($request, $postId, true);

        if ($post->deleted_at !== null) {
            $post->restore();
        }

        $post->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'posted_at' => $post->posted_at ?? now(),
        ])->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'Post reposted successfully.',
            'post' => $this->postPayload($post->fresh(['media', 'tags'])),
        ]);
    }

    private function validatePost(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? ['sometimes'] : ['required'];

        return $request->validate([
            'post_type' => array_merge($required, ['string', Rule::in(['1', '2', 'outfit', 'food'])]),
            'file' => $isUpdate
                ? ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi,mkv,webm']
                : ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi,mkv,webm'],
            'caption' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', 'string', Rule::in(['public', 'private', 'followers_only'])],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'rating_enabled' => ['nullable', 'boolean'],
            'dish_name' => ['nullable', 'string', 'max:191'],
            'restaurant' => ['nullable', 'string', 'max:191'],
            'food_rating' => ['nullable', 'integer', 'between:1,5'],
            'service_rating' => ['nullable', 'integer', 'between:1,5'],
            'staff_rating' => ['nullable', 'integer', 'between:1,5'],
            'ambience_rating' => ['nullable', 'integer', 'between:1,5'],
        ]);
    }

    private function normalizePostType(string $postType): string
    {
        return match ($postType) {
            '1' => 'outfit',
            '2' => 'food',
            default => $postType,
        };
    }

    private function validatePostTypeFields(Request $request, string $postType, bool $isUpdate = false): void
    {
        if ($postType === 'food') {
            $request->validate([
                'dish_name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:191'],
                'restaurant' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:191'],
                'food_rating' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'between:1,5'],
                'service_rating' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'between:1,5'],
                'staff_rating' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'between:1,5'],
                'ambience_rating' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'between:1,5'],
            ]);
        }
    }

    private function syncTags(?string $tags): array
    {
        if (! $tags) {
            return [];
        }

        $names = collect(explode(',', $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique(fn (string $tag) => Str::lower($tag))
            ->values();

        $tagIds = [];

        foreach ($names as $name) {
            $normalizedName = Str::lower($name);

            $tag = Tag::query()->firstOrCreate(
                ['normalized_name' => $normalizedName],
                ['name' => $name, 'usage_count' => 0]
            );

            $tag->increment('usage_count');
            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }

    private function attachTagsToPost(Post $post, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $now = now();

        PostTag::query()->insert(
            collect($tagIds)->map(fn (int $tagId) => [
                'post_id' => $post->id,
                'tag_id' => $tagId,
                'created_at' => $now,
            ])->all()
        );
    }

    private function replacePostMedia(Post $post, int $userId, $file): void
    {
        $mediaType = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
        $uploadedFile = stylebite_upload_file($file, 'posts/'.$userId);

        $upload = MediaUpload::create([
            'user_id' => $userId,
            'source' => 'gallery',
            'upload_type' => 'post_media',
            'media_type' => $mediaType,
            'original_file_name' => $uploadedFile['original_file_name'],
            'file_path' => $uploadedFile['file_path'],
            'file_url' => $uploadedFile['file_url'],
            'mime_type' => $uploadedFile['mime_type'],
            'size_bytes' => $uploadedFile['size_bytes'],
            'storage_type' => 'local',
            'upload_status' => 'ready',
            'uploaded_at' => now(),
        ]);

        $post->media()->delete();

        $media = PostMedia::create([
            'post_id' => $post->id,
            'upload_id' => $upload->id,
            'media_type' => $mediaType,
            'media_role' => 'original',
            'file_path' => $uploadedFile['file_path'],
            'file_url' => $uploadedFile['file_url'],
            'mime_type' => $uploadedFile['mime_type'],
            'size_bytes' => $uploadedFile['size_bytes'],
            'storage_type' => 'local',
            'sort_order' => 0,
            'processing_status' => 'pending',
        ]);

        $post->forceFill([
            'media_kind' => $mediaType,
        ])->save();

        OptimizePostMedia::dispatch($media->id)->afterCommit();
    }

    private function resolveLocationDetails(array $validated): array
    {
        $lat = array_key_exists('lat', $validated) ? (float) $validated['lat'] : null;
        $lng = array_key_exists('lng', $validated) ? (float) $validated['lng'] : null;

        if ($lat === null || $lng === null) {
            return [
                'lat' => null,
                'lng' => null,
                'city' => null,
                'country' => null,
                'location_name' => null,
            ];
        }

        $apiKey = config('services.google_maps.api_key');

        $fallback = [
            'lat' => $lat,
            'lng' => $lng,
            'city' => null,
            'country' => null,
            'location_name' => null,
        ];

        if (! $apiKey) {
            return $this->resolveLocationWithOpenStreetMap($lat, $lng, $fallback);
        }

        $response = Http::timeout(10)
            ->get(config('services.google_maps.geocode_url'), [
                'latlng' => $lat.','.$lng,
                'key' => $apiKey,
                'language' => 'en',
            ]);

        if (! $response->successful()) {
            return $this->resolveLocationWithOpenStreetMap($lat, $lng, $fallback);
        }

        $payload = $response->json();

        $results = collect($payload['results'] ?? []);

        if (($payload['status'] ?? null) !== 'OK' || $results->isEmpty()) {
            return $this->resolveLocationWithOpenStreetMap($lat, $lng, $fallback);
        }

        $city = null;
        $country = null;
        $locationName = $results->first()['formatted_address'] ?? null;

        foreach ($results as $result) {
            $components = collect($result['address_components'] ?? []);

            $resolvedCity = $this->findAddressComponent($components, [
                'locality',
                'postal_town',
                'administrative_area_level_3',
                'administrative_area_level_2',
                'administrative_area_level_1',
                'sublocality',
                'neighborhood',
            ]);

            $resolvedCountry = $this->findAddressComponent($components, [
                'country',
            ]);

            $city ??= $resolvedCity;
            $country ??= $resolvedCountry;

            if ($resolvedCity && $resolvedCountry) {
                $locationName = $result['formatted_address'] ?? $locationName;
                break;
            }
        }

        if (! $city || ! $country) {
            [$parsedCity, $parsedCountry] = $this->parseFormattedAddress((string) $locationName);
            $city ??= $parsedCity;
            $country ??= $parsedCountry;
        }

        if (! $city || ! $country) {
            $fallback['location_name'] = $locationName;

            return $this->resolveLocationWithOpenStreetMap($lat, $lng, $fallback);
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'city' => $city,
            'country' => $country,
            'location_name' => $locationName,
        ];
    }

    private function resolveLocationWithOpenStreetMap(float $lat, float $lng, array $fallback): array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => config('app.name', 'Stylebite').'/1.0',
            ])
            ->get(config('services.openstreetmap.reverse_geocode_url'), [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'jsonv2',
                'accept-language' => 'en',
            ]);

        if (! $response->successful()) {
            return $fallback;
        }

        $payload = $response->json();
        $address = $payload['address'] ?? [];

        $city = $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? $address['county']
            ?? $address['state_district']
            ?? $address['state']
            ?? null;

        $country = $address['country'] ?? null;
        $locationName = $payload['display_name'] ?? $fallback['location_name'];

        if ((! $city || ! $country) && $locationName) {
            [$parsedCity, $parsedCountry] = $this->parseFormattedAddress($locationName);
            $city ??= $parsedCity;
            $country ??= $parsedCountry;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'city' => $city,
            'country' => $country,
            'location_name' => $locationName,
        ];
    }

    private function findAddressComponent($components, array $types): ?string
    {
        foreach ($types as $type) {
            $match = $components->first(function (array $component) use ($type) {
                return in_array($type, $component['types'] ?? [], true);
            });

            if ($match) {
                return $match['long_name'] ?? null;
            }
        }

        return null;
    }

    private function parseFormattedAddress(string $formattedAddress): array
    {
        $parts = collect(explode(',', $formattedAddress))
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return [null, null];
        }

        $country = $parts->last();
        $city = $parts->count() > 1 ? $parts->first() : null;

        return [$city, $country];
    }

    private function postPayload(Post $post): array
    {
        return [
            'id' => $post->id,
            'post_type' => $post->post_type,
            'caption' => $post->caption,
            'location' => $post->location_name,
            'visibility' => $post->visibility,
            'lat' => $post->location_lat,
            'lng' => $post->location_lng,
            'city' => $post->city,
            'country' => $post->country,
            'dish_name' => $post->dish_name,
            'restaurant' => $post->restaurant,
            'rating_enabled' => (bool) $post->rating_enabled,
            'food_rating' => $post->food_rating,
            'service_rating' => $post->service_rating,
            'staff_rating' => $post->staff_rating,
            'ambience_rating' => $post->ambience_rating,
            'media' => $post->media->map(fn (PostMedia $media) => [
                'id' => $media->id,
                'media_type' => $media->media_type,
                'file_url' => stylebite_asset_url($media->file_url),
            ])->values()->all(),
            'tags' => $post->tags->pluck('name')->values()->all(),
            'posted_at' => $post->posted_at?->toISOString(),
        ];
    }

    private function findOwnedPost(Request $request, int $postId, bool $withTrashed = false): Post
    {
        $query = Post::query()
            ->with(['media', 'tags'])
            ->where('user_id', $request->user()->id);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($postId);
    }
}
