<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Client-facing timestamps: always ISO-8601 with an offset so Flutter can
 * convert to the device's local timezone. Bare "Y-m-d H:i:s" strings break
 * that conversion (the app wrongly treats them as UTC).
 */
final class ApiDateTime
{
    public static function iso(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
