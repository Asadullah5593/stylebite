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
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
