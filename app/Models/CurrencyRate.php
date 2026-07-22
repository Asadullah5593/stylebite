<?php

namespace App\Models;

class CurrencyRate extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'rate_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }
}
