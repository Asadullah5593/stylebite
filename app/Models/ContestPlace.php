<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestPlace extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'place' => 'integer',
            'prize_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function winnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public static function ordinal(int $place): string
    {
        $mod100 = $place % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return $place.'th';
        }

        return $place.match ($place % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    public function displayTitle(): string
    {
        $title = trim((string) $this->prize_title);

        return $title !== '' ? $title : self::ordinal((int) $this->place).' place';
    }
}
