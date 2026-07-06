<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends StylebiteModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'location_lat' => 'decimal:7',
            'location_lng' => 'decimal:7',
            'allow_comments' => 'boolean',
            'allow_shares' => 'boolean',
            'rating_enabled' => 'boolean',
            'is_reported' => 'boolean',
            'is_blocked' => 'boolean',
            'rating_avg' => 'decimal:2',
            'food_rating' => 'integer',
            'service_rating' => 'integer',
            'staff_rating' => 'integer',
            'ambience_rating' => 'integer',
            'posted_at' => 'datetime',
            'published_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    public function postTags(): HasMany
    {
        return $this->hasMany(PostTag::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags')
            ->using(PostTag::class)
            ->withPivot(['id', 'created_at']);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(PostRating::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(PostShare::class);
    }

    public function latestShare(): HasOne
    {
        return $this->hasOne(PostShare::class)->latestOfMany('created_at');
    }

    public function saves(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    public function contestSubmissions(): HasMany
    {
        return $this->hasMany(ContestSubmission::class);
    }
}
