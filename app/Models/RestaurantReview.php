<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantReview extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'food_rating' => 'float',
            'service_rating' => 'float',
            'staff_rating' => 'float',
            'ambience_rating' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public static function normalizeKey(string $raw): string
    {
        $value = mb_strtolower(trim($raw));
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? '';
        $value = preg_replace('/\b(restaurant|cafe|café|grill|kitchen|dhaba|hotel)\b/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    public function overall(): float
    {
        $values = array_values(array_filter(
            [$this->food_rating, $this->service_rating, $this->staff_rating, $this->ambience_rating],
            fn ($value) => (float) $value > 0,
        ));

        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * Bite posts store ratings on `posts`; the app shows those as reviews.
     * Mirror them into `restaurant_reviews` so admin/API see the same data.
     * Existing community reviews (same user + restaurant) are left alone.
     */
    public static function syncFromFoodPost(Post $post): ?self
    {
        if ($post->post_type !== 'food') {
            return null;
        }

        $restaurant = trim((string) $post->restaurant);
        $key = self::normalizeKey($restaurant);
        if ($key === '' || $post->food_rating === null) {
            return null;
        }

        return self::query()->firstOrCreate(
            [
                'user_id' => $post->user_id,
                'restaurant_key' => $key,
            ],
            [
                'restaurant' => $restaurant,
                'post_id' => $post->id,
                'food_rating' => (float) $post->food_rating,
                'service_rating' => (float) ($post->service_rating ?? $post->food_rating),
                'staff_rating' => (float) ($post->staff_rating ?? $post->food_rating),
                'ambience_rating' => (float) ($post->ambience_rating ?? $post->food_rating),
                'created_at' => $post->posted_at ?? $post->created_at ?? now(),
                'updated_at' => $post->updated_at ?? now(),
            ],
        );
    }

    public static function syncMissingFromFoodPosts(): int
    {
        $created = 0;

        Post::query()
            ->where('post_type', 'food')
            ->whereNotNull('restaurant')
            ->where('restaurant', '!=', '')
            ->whereNotNull('food_rating')
            ->where('status', '!=', 'removed')
            ->orderBy('id')
            ->chunkById(100, function ($posts) use (&$created): void {
                foreach ($posts as $post) {
                    $before = self::query()
                        ->where('user_id', $post->user_id)
                        ->where('restaurant_key', self::normalizeKey((string) $post->restaurant))
                        ->exists();

                    $review = self::syncFromFoodPost($post);
                    if ($review && ! $before) {
                        $created++;
                    }
                }
            });

        return $created;
    }
}
