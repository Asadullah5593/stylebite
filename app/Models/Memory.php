<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Memory extends StylebiteModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'memory_date' => 'date',
            'location_lat' => 'decimal:7',
            'location_lng' => 'decimal:7',
            'rating' => 'decimal:2',
            'rating_count' => 'integer',
            'is_favorite' => 'boolean',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'save_count' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(MemoryMedia::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(SavedMemory::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(MemoryLike::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(MemoryRating::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MemoryComment::class);
    }
}
