<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
            'is_story' => 'boolean',
            'rating_avg' => 'decimal:2',
            'food_rating' => 'integer',
            'service_rating' => 'integer',
            'staff_rating' => 'integer',
            'ambience_rating' => 'integer',
            'posted_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function restaurantReviews(): HasMany
    {
        return $this->hasMany(RestaurantReview::class);
    }

    public function contestSubmissions(): HasMany
    {
        return $this->hasMany(ContestSubmission::class);
    }

    public function latestContestSubmission(): HasOne
    {
        return $this->hasOne(ContestSubmission::class)->latestOfMany();
    }

    /**
     * Eager-loads everything the API needs to describe a post's contest
     * state (`in_contest` flag plus the contest badge) without N+1 queries.
     */
    public function scopeWithContestState(Builder $query): Builder
    {
        return $query
            ->withExists('contestSubmissions')
            ->with('latestContestSubmission.contest');
    }

    /**
     * Small badge payload for feed/profile cards: which contest this post
     * is entered in. Null when the post is not in any contest.
     *
     * @return array{id:int,title:string,status:string}|null
     */
    public function contestBadge(): ?array
    {
        if (! $this->isInContest()) {
            return null;
        }

        $submission = $this->relationLoaded('latestContestSubmission')
            ? $this->latestContestSubmission
            : $this->latestContestSubmission()->with('contest')->first();
        $contest = $submission?->contest;

        if (! $contest) {
            return null;
        }

        return [
            'id' => (int) $contest->id,
            'title' => (string) $contest->title,
            'status' => (string) $contest->status,
        ];
    }

    public function isInContest(): bool
    {
        if (array_key_exists('contest_submissions_exists', $this->getAttributes())) {
            return (bool) $this->getAttribute('contest_submissions_exists');
        }

        if ($this->relationLoaded('contestSubmissions')) {
            return $this->contestSubmissions->isNotEmpty();
        }

        return $this->contestSubmissions()->exists();
    }
}
