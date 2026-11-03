<?php

namespace App\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Contest schedule times are the clock time entered in admin, in Pakistan
 * (Asia/Karachi, GMT+5). The database stores that clock without converting
 * it to UTC, so a 12:00 PM start stays 12:00 PM on a Pakistan phone.
 */
class PakistanTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, 'Asia/Karachi');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = $value instanceof DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse($value, 'Asia/Karachi');

        return $time->timezone('Asia/Karachi')->format('Y-m-d H:i:s');
    }
}
