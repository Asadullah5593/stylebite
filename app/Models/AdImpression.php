<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdImpression extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:8',
            'share_percent' => 'decimal:2',
            'owner_share' => 'decimal:8',
            'admin_share' => 'decimal:8',
            'viewed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function settledTransaction(): BelongsTo
    {
        return $this->belongsTo(EarningTransaction::class, 'settled_transaction_id');
    }
}
