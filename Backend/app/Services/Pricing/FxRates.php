<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Support\Currencies;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Exchange rates against USD, refreshed by the scheduler and cached. The last good set is kept in
 * the database, so an outage of the rates service never breaks pricing: it just uses older rates.
 */
final class FxRates
{
    private const string CACHE_KEY = 'fx_rates.v1';

    private const int CACHE_SECONDS = 12 * 3600;

    /** @return array<string, float> units per 1 USD, keyed by currency */
    public function all(): array
    {
        /** @var array<string, float> */
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static function (): array {
            $rates = ['USD' => 1.0];
            foreach (DB::table('fx_rates')->get(['currency', 'per_usd']) as $row) {
                $rates[(string) $row->currency] = (float) $row->per_usd;
            }

            return $rates;
        });
    }

    public function rate(string $currency): ?float
    {
        $rate = $this->all()[strtoupper($currency)] ?? null;

        return $rate !== null && $rate > 0 ? $rate : null;
    }

    public function fetchedAt(): ?string
    {
        $at = DB::table('fx_rates')->max('fetched_at');

        return is_string($at) ? $at : null;
    }

    /** Converts minor units between currencies (null when a rate is missing). */
    public function convert(int $minor, string $from, string $to): ?int
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return $minor;
        }
        $fromRate = $this->rate($from);
        $toRate = $this->rate($to);
        if ($fromRate === null || $toRate === null) {
            return null;
        }

        return (int) round($minor / $fromRate * $toRate);
    }

    /** Pulls fresh rates. Returns how many currencies were stored, or null if the service failed. */
    public function refresh(): ?int
    {
        $url = (string) config('vendslip.fx.url', 'https://open.er-api.com/v6/latest/USD');
        try {
            $response = Http::acceptJson()->timeout(15)->retry(2, 500, throw: false)->get($url);
        } catch (ConnectionException $e) {
            Log::warning('fx.refresh_failed', ['error' => $e->getMessage()]);

            return null;
        }
        $rates = $response->json('rates');
        if (! $response->successful() || $response->json('result', 'success') !== 'success' || ! is_array($rates) || ($rates['USD'] ?? null) != 1) {
            Log::warning('fx.refresh_failed', ['status' => $response->status()]);

            return null;
        }

        $now = now();
        $rows = [];
        foreach ($rates as $code => $rate) {
            $code = strtoupper((string) $code);
            if ($code === 'USD' || ! Currencies::exists($code) || ! is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 1e9) {
                continue;
            }
            $rows[] = ['currency' => $code, 'per_usd' => (float) $rate, 'source' => parse_url($url, PHP_URL_HOST) ?: 'rates', 'fetched_at' => $now];
        }
        if ($rows === []) {
            return null;
        }
        try {
            DB::table('fx_rates')->upsert($rows, ['currency'], ['per_usd', 'source', 'fetched_at']);
        } catch (Throwable $e) {
            Log::error('fx.store_failed', ['error' => $e->getMessage()]);

            return null;
        }
        Cache::forget(self::CACHE_KEY);

        return count($rows);
    }
}
