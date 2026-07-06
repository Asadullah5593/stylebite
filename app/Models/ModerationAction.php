<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationAction extends StylebiteModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_user_id');
    }
}
