<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends StylebiteModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_verified_badge' => 'boolean',
            'is_private' => 'boolean',
            'style_points' => 'integer',
            'current_streak_days' => 'integer',
            'contest_wins' => 'integer',
            'contest_entries' => 'integer',
            'battle_wins' => 'integer',
            'last_known_lat' => 'decimal:7',
            'last_known_lng' => 'decimal:7',
            'last_located_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
