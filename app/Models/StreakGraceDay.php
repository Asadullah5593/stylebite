<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A day an admin credited to a user's streak after it broke.
 *
 * The streak engine treats a grace day exactly like a day the user was active,
 * which is what lets a restore survive every later recomputation.
 */
class StreakGraceDay extends StylebiteModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'grace_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
