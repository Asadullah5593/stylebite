<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EarningTransaction extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'fx_rate' => 'decimal:10',
            'fx_rate_at' => 'datetime',
            'metadata_json' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(EarningsWallet::class, 'wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
