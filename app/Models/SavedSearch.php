<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
