<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\RestaurantReview;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RestaurantReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant' => ['nullable', 'string', 'max:191'],
            'q' => ['nullable', 'string', 'max:191'],
        ]);

        $restaurant = trim((string) ($validated['restaurant'] ?? $validated['q'] ?? ''));

        return $this->reviewsResponse(
            $request->user(),
            $restaurant,
            'Restaurant reviews fetched successfully.',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRatings($request, restaurantRequired: true);
        $post = $this->optionalFoodPost((int) ($validated['post_id'] ?? 0));

        $review = $this->upsertReview(
            $request->user(),
            $validated['restaurant'],
            $validated,
            $post,
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Restaurant review saved successfully.',
            'review' => $this->reviewPayload($review),
        ]);
    }

    public function postReviews(Request $request, int $postId): JsonResponse
    {
        $post = $this->findPublishedFoodPost($postId, abortIfMissing: false);
        $restaurant = $post?->restaurant ?? '';

        return $this->reviewsResponse(
            $request->user(),
            $restaurant,
            'Food reviews fetched successfully.',
            $post,
        );
    }

    public function storePostReview(Request $request, int $postId): JsonResponse
    {
        $post = $this->findPublishedFoodPost($postId, abortIfMissing: true);
        $validated = $this->validateRatings($request, restaurantRequired: false);
        $restaurant = trim((string) ($validated['restaurant'] ?? $post->restaurant ?? ''));

        if ($restaurant === '') {
            return response()->json([
                'status_code' => 0,
                'message' => 'This post has no restaurant to review.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $review = $this->upsertReview(
            $request->user(),
            $restaurant,
            $validated,
            $post,
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Food review saved successfully.',
            'review' => $this->reviewPayload($review),
        ]);
    }

    private function validateRatings(Request $request, bool $restaurantRequired): array
    {
        return $request->validate([
            'restaurant' => [$restaurantRequired ? 'required' : 'nullable', 'string', 'max:191'],
            'food_rating' => ['required', 'numeric', 'between:1,5'],
            'service_rating' => ['required', 'numeric', 'between:1,5'],
            'staff_rating' => ['required', 'numeric', 'between:1,5'],
            'ambience_rating' => ['required', 'numeric', 'between:1,5'],
            'post_id' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function upsertReview(User $user, string $restaurant, array $validated, ?Post $post): RestaurantReview
    {
        $restaurant = trim($restaurant);
        $key = RestaurantReview::normalizeKey($restaurant);

        if ($key === '') {
            abort(response()->json([
                'status_code' => 0,
                'message' => 'This post has no restaurant to review.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return DB::transaction(function () use ($user, $restaurant, $key, $validated, $post): RestaurantReview {
            $review = RestaurantReview::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'restaurant_key' => $key,
                ],
                [
                    'restaurant' => $restaurant,
                    'post_id' => $post?->id,
                    'food_rating' => $this->normalizeRating($validated['food_rating']),
                    'service_rating' => $this->normalizeRating($validated['service_rating']),
                    'staff_rating' => $this->normalizeRating($validated['staff_rating']),
                    'ambience_rating' => $this->normalizeRating($validated['ambience_rating']),
                ],
            );

            return $review->load(['user.profile', 'post']);
        });
    }

    private function reviewsResponse(?User $viewer, string $restaurant, string $message, ?Post $post = null): JsonResponse
    {
        $key = RestaurantReview::normalizeKey($restaurant);
        $reviews = collect();

        if ($key !== '') {
            $query = RestaurantReview::query()
                ->with(['user.profile', 'post'])
                ->where('restaurant_key', $key)
                ->orderByDesc('updated_at');

            $this->applyReviewerBlockScope($query, $viewer);
            $reviews = $query->get();
        }

        $payloads = $reviews->map(fn (RestaurantReview $review) => $this->reviewPayload($review))->values();

        return response()->json([
            'status_code' => 1,
            'message' => $message,
            'restaurant' => $restaurant !== '' ? $restaurant : ($post?->restaurant ?? ''),
            'reviews' => $payloads,
            'summary' => $this->summaryPayload($reviews),
        ]);
    }

    private function reviewPayload(RestaurantReview $review): array
    {
        $user = $review->user;
        $profile = $user?->profile;
        $username = $user?->username ?? '';
        $avatarUrl = stylebite_asset_url($user?->avatar_url);

        return [
            'review_id' => $review->id,
            'restaurant' => $review->restaurant,
            'restaurant_name' => $review->restaurant,
            'username' => $username,
            'user_name' => $username,
            'display_name' => $profile?->display_name ?? $user?->full_name,
            'avatar_url' => $avatarUrl,
            'post_id' => $review->post_id,
            'food_rating' => (float) $review->food_rating,
            'service_rating' => (float) $review->service_rating,
            'staff_rating' => (float) $review->staff_rating,
            'ambience_rating' => (float) $review->ambience_rating,
            'overall' => round($review->overall(), 2),
            'source' => 'remote',
            'created_at' => optional($review->created_at)?->toIso8601String(),
            'author' => [
                'id' => $user?->id,
                'username' => $username,
                'display_name' => $profile?->display_name ?? $user?->full_name,
                'avatar_url' => $avatarUrl,
            ],
        ];
    }

    private function summaryPayload($reviews): array
    {
        if ($reviews->isEmpty()) {
            return [
                'food' => 0,
                'service' => 0,
                'staff' => 0,
                'ambience' => 0,
                'overall' => 0,
                'count' => 0,
            ];
        }

        return [
            'food' => round($reviews->avg('food_rating'), 2),
            'service' => round($reviews->avg('service_rating'), 2),
            'staff' => round($reviews->avg('staff_rating'), 2),
            'ambience' => round($reviews->avg('ambience_rating'), 2),
            'overall' => round($reviews->avg(fn (RestaurantReview $review) => $review->overall()), 2),
            'count' => $reviews->count(),
        ];
    }

    private function applyReviewerBlockScope(Builder $query, ?User $viewer): void
    {
        if (! $viewer) {
            return;
        }

        $blockedIds = UserBlock::query()
            ->where(function (Builder $blockQuery) use ($viewer): void {
                $blockQuery
                    ->where('blocker_user_id', $viewer->id)
                    ->orWhere('blocked_user_id', $viewer->id);
            })
            ->get()
            ->flatMap(fn (UserBlock $block) => [$block->blocker_user_id, $block->blocked_user_id])
            ->reject(fn (int $userId) => $userId === $viewer->id)
            ->unique()
            ->values();

        if ($blockedIds->isNotEmpty()) {
            $query->whereNotIn('user_id', $blockedIds);
        }
    }

    private function optionalFoodPost(int $postId): ?Post
    {
        if ($postId <= 0) {
            return null;
        }

        return Post::query()
            ->whereKey($postId)
            ->where('post_type', 'food')
            ->where('status', 'published')
            ->where('is_blocked', false)
            ->first();
    }

    private function findPublishedFoodPost(int $postId, bool $abortIfMissing): ?Post
    {
        $post = Post::query()
            ->whereKey($postId)
            ->where('status', 'published')
            ->where('is_blocked', false)
            ->first();

        if (! $post) {
            if (! $abortIfMissing) {
                return null;
            }

            abort(response()->json([
                'status_code' => 0,
                'message' => 'Requested post was not found.',
            ], Response::HTTP_NOT_FOUND));
        }

        if ($post->post_type !== 'food') {
            abort(response()->json([
                'status_code' => 0,
                'message' => 'Food reviews are only available on Bite posts.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $post;
    }

    private function normalizeRating(mixed $value): float
    {
        return round(((float) $value) * 2) / 2;
    }
}
