<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomatedNotificationSend extends StylebiteModel
{
    public const KIND_STREAK_REMINDER = 'streak_reminder';

    public const KIND_CONTEST_ENDING_SOON = 'contest_ending_soon';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
