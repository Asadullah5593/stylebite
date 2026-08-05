<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user per active day — the source for DAU/MAU on the admin dashboard.
 */
class UserDailyActivity extends StylebiteModel
{
    protected $table = 'user_daily_activity';

    // Written once per user per day with an explicit created_at; there is
    // nothing to update afterwards, so Eloquent timestamps stay off.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
