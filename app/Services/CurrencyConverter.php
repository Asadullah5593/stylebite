<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Converts money between currencies using rates stored in the database by
 * the stylebite:sync-currency-rates command.
 *
 * This NEVER calls an external API — rates come from the DB only, so a
 * request can't block on (or fail because of) a third-party FX provider.
 * Conversions are meant to be performed once, at credit time, and then
 * frozen on the transaction; balances are never re-converted afterwards.
 */
class CurrencyConverter
{
    public const BASE_CURRENCY = 'USD';

    private const CACHE_KEY = 'stylebite_currency_rates';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * The base currency all rewards are defined in (admin-configurable).
     */
    public function baseCurrency(): string
    {
        $configured = stylebite_app_config('earnings.base_currency_code', self::BASE_CURRENCY);

        return Str::upper(trim((string) $configured)) ?: self::BASE_CURRENCY;
    }

    /**
     * Convert an amount between two currencies.
     *
     * @return array{amount:float,rate:float,rate_at:?\Illuminate\Support\Carbon}|null
     *                                                                                 Null when no rate is available — callers must treat this as a hard
     *                                                                                 failure rather than crediting an unconverted amount.
     */
    public function convert(float $amount, string $from, string $to): ?array
    {
        $from = Str::upper(trim($from));
        $to = Str::upper(trim($to));

        if ($from === '' || $to === '') {
            return null;
        }

        if ($from === $to) {
            return [
                'amount' => round($amount, 2),
                'rate' => 1.0,
                'rate_at' => null,
            ];
        }

        $rateRow = $this->rateRow($from, $to);

        if ($rateRow === null) {
            return null;
        }

        return [
            'amount' => round($amount * (float) $rateRow['rate'], 2),
            'rate' => (float) $rateRow['rate'],
            'rate_at' => $rateRow['rate_at'],
        ];
    }

    /**
     * Whether a usable rate exists for this pair.
     */
    public function hasRate(string $from, string $to): bool
    {
        return Str::upper(trim($from)) === Str::upper(trim($to))
            || $this->rateRow(Str::upper(trim($from)), Str::upper(trim($to))) !== null;
    }

    /**
     * When the rates currently in use were fetched (null if none stored yet).
     */
    public function ratesFetchedAt(): ?\Illuminate\Support\Carbon
    {
        $latest = CurrencyRate::query()->max('fetched_at');

        return $latest !== null ? \Illuminate\Support\Carbon::parse($latest) : null;
    }

    /**
     * @return array{rate:float,rate_at:?\Illuminate\Support\Carbon}|null
     */
    private function rateRow(string $from, string $to): ?array
    {
        $rates = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => CurrencyRate::query()
                ->get(['base_currency_code', 'target_currency_code', 'rate', 'rate_at'])
                ->keyBy(fn (CurrencyRate $rate) => $rate->base_currency_code.'_'.$rate->target_currency_code)
                ->map(fn (CurrencyRate $rate) => [
                    'rate' => (float) $rate->rate,
                    'rate_at' => optional($rate->rate_at)->toIso8601String(),
                ])
                ->all()
        );

        $direct = $rates[$from.'_'.$to] ?? null;

        if ($direct !== null) {
            return $this->hydrate($direct['rate'], $direct['rate_at']);
        }

        // Inverse (e.g. stored USD->PKR, asked PKR->USD).
        $inverse = $rates[$to.'_'.$from] ?? null;

        if ($inverse !== null && (float) $inverse['rate'] > 0) {
            return $this->hydrate(1 / (float) $inverse['rate'], $inverse['rate_at']);
        }

        // Cross rate via the stored base (e.g. PKR->GBP using USD->PKR & USD->GBP).
        $base = self::BASE_CURRENCY;
        $baseToFrom = $rates[$base.'_'.$from] ?? null;
        $baseToTo = $rates[$base.'_'.$to] ?? null;

        if ($baseToFrom !== null && $baseToTo !== null && (float) $baseToFrom['rate'] > 0) {
            return $this->hydrate(
                (float) $baseToTo['rate'] / (float) $baseToFrom['rate'],
                $baseToTo['rate_at']
            );
        }

        return null;
    }

    /**
     * @return array{rate:float,rate_at:?\Illuminate\Support\Carbon}
     */
    private function hydrate(float $rate, ?string $rateAt): array
    {
        return [
            'rate' => $rate,
            'rate_at' => $rateAt !== null ? \Illuminate\Support\Carbon::parse($rateAt) : null,
        ];
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
