<?php

namespace App\Console\Commands;

use App\Models\CurrencyRate;
use App\Services\CurrencyConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches the daily FX rates used to convert rewards into each creator's
 * wallet currency, and stores them in the currency_rates table.
 *
 * Runs from cron (once a day is enough — the source updates daily). On
 * failure the previously stored rates are left untouched, so crediting keeps
 * working with the last known-good rates rather than breaking.
 *
 * Rates: ExchangeRate-API Open Access endpoint (no API key required).
 * https://www.exchangerate-api.com — attribution required for open access.
 */
class SyncCurrencyRatesCommand extends Command
{
    protected $signature = 'stylebite:sync-currency-rates
        {--base= : Base currency to fetch (defaults to the configured earnings base)}';

    protected $description = 'Fetch and store the latest FX rates used for creator earnings conversions.';

    public function handle(CurrencyConverter $converter): int
    {
        $base = strtoupper((string) ($this->option('base') ?: $converter->baseCurrency()));
        $endpoint = rtrim((string) config('services.exchange_rates.base_url'), '/').'/'.$base;

        $this->info("Fetching rates for base {$base}...");

        try {
            $response = Http::timeout(20)->acceptJson()->get($endpoint);
        } catch (\Throwable $exception) {
            return $this->fail_('Request failed: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            return $this->fail_('HTTP '.$response->status().' from rate provider.');
        }

        $payload = $response->json();

        if (($payload['result'] ?? null) !== 'success' || ! is_array($payload['rates'] ?? null)) {
            return $this->fail_('Unexpected payload from rate provider.');
        }

        $rateAt = isset($payload['time_last_update_unix'])
            ? Carbon::createFromTimestampUTC((int) $payload['time_last_update_unix'])
            : null;
        $fetchedAt = now();
        $stored = 0;

        foreach ($payload['rates'] as $target => $rate) {
            if (! is_numeric($rate) || (float) $rate <= 0 || strlen((string) $target) !== 3) {
                continue;
            }

            CurrencyRate::query()->updateOrCreate(
                [
                    'base_currency_code' => $base,
                    'target_currency_code' => strtoupper((string) $target),
                ],
                [
                    'rate' => (float) $rate,
                    'source' => 'open.er-api.com',
                    'rate_at' => $rateAt,
                    'fetched_at' => $fetchedAt,
                ]
            );

            $stored++;
        }

        CurrencyConverter::forgetCache();

        $this->info("Stored {$stored} rates (rate date: ".($rateAt?->toDateString() ?? 'unknown').').');

        return self::SUCCESS;
    }

    private function fail_(string $message): int
    {
        $existing = CurrencyRate::query()->count();

        Log::warning('Currency rate sync failed.', [
            'message' => $message,
            'existing_rates_kept' => $existing,
        ]);

        $this->error('Rate sync failed: '.$message);
        $this->warn($existing > 0
            ? "Keeping {$existing} previously stored rates — crediting still works."
            : 'No rates stored yet — crediting will be blocked until a sync succeeds.');

        return self::FAILURE;
    }
}
