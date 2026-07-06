<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestLeaderboardSnapshot extends StylebiteModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }
}
