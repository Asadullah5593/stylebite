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
}
