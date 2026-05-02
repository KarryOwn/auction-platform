<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    private const CACHE_KEY = 'exchange_rates:v1';
    private const CACHE_TTL = 3600;
    private const STALE_HOURS = 24;

    public function getRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $rates = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->loadRates());

        if (isset($rates["{$from}_{$to}"])) {
            return (float) $rates["{$from}_{$to}"];
        }

        if ($from !== 'USD' && isset($rates["USD_{$from}"], $rates["USD_{$to}"])) {
            $fromRate = (float) $rates["USD_{$from}"];
            $toRate = (float) $rates["USD_{$to}"];

            if ($fromRate > 0) {
                return $toRate / $fromRate;
            }
        }

        return 1.0;
    }

    public function convert(float $amount, string $from, string $to): float
    {
        $to = strtoupper($to);
        $decimals = in_array($to, ['JPY', 'VND'], true) ? 0 : 2;

        return round($amount * $this->getRate($from, $to), $decimals);
    }

    public function refresh(): void
    {
        $apiKey = config('services.exchange_rate.api_key');
        $apiUrl = config('services.exchange_rate.url', 'https://api.exchangeratesapi.io/v1/latest');
        $supported = array_values(array_unique(array_map('strtoupper', config('auction.supported_currencies', ['USD']))));

        try {
            $query = [
                'symbols' => implode(',', $supported),
            ];

            if (! empty($apiKey)) {
                $query['access_key'] = $apiKey;
            }

            $response = Http::timeout(10)->get($apiUrl, $query);

            if (! $response->ok()) {
                throw new \RuntimeException("Exchange rate API returned {$response->status()}");
            }

            $data = $response->json();
            $rates = $data['rates'] ?? [];
            $base = strtoupper($data['base'] ?? 'EUR');

            if ($base === 'USD') {
                $usdRate = 1.0;
            } elseif (isset($rates['USD']) && (float) $rates['USD'] > 0) {
                $usdRate = (float) $rates['USD'];
            } else {
                throw new \RuntimeException('Exchange rate API response is missing a usable USD rate.');
            }

            foreach ($supported as $currency) {
                if ($currency === 'USD') {
                    continue;
                }

                if (! isset($rates[$currency])) {
                    continue;
                }

                $quote = (float) $rates[$currency];
                $rate = $base === 'USD' ? $quote : round($quote / $usdRate, 8);

                ExchangeRate::updateOrCreate(
                    ['base_currency' => 'USD', 'target_currency' => $currency],
                    ['rate' => $rate, 'fetched_at' => now()]
                );
            }

            Cache::forget(self::CACHE_KEY);

            Log::info('ExchangeRateService: rates refreshed', ['currencies' => $supported]);
        } catch (\Throwable $e) {
            Log::error('ExchangeRateService: refresh failed', ['error' => $e->getMessage()]);
        }
    }

    private function loadRates(): array
    {
        $rates = [
            'USD_USD' => 1.0,
        ];

        foreach ($this->fallbackRates() as $target => $rate) {
            $rates["USD_{$target}"] = $rate;
            $rates["{$target}_USD"] = 1 / $rate;
        }

        ExchangeRate::query()
            ->where('base_currency', 'USD')
            ->where('fetched_at', '>=', now()->subHours(self::STALE_HOURS))
            ->get()
            ->each(function (ExchangeRate $rate) use (&$rates): void {
                $forward = (float) $rate->rate;

                if ($forward <= 0) {
                    return;
                }

                $target = strtoupper($rate->target_currency);
                $rates["USD_{$target}"] = $forward;
                $rates["{$target}_USD"] = 1 / $forward;
            });

        return $rates;
    }

    /**
     * Local display-rate fallback used when the external provider is not configured
     * or recent DB rates are unavailable. Fresh DB rates still override these values.
     *
     * @return array<string, float>
     */
    private function fallbackRates(): array
    {
        $configured = config('services.exchange_rate.fallback_rates', []);
        $supported = array_map('strtoupper', config('auction.supported_currencies', ['USD']));
        $rates = [];

        foreach ($configured as $currency => $rate) {
            $currency = strtoupper((string) $currency);
            $rate = (float) $rate;

            if ($currency === 'USD' || $rate <= 0 || ! in_array($currency, $supported, true)) {
                continue;
            }

            $rates[$currency] = $rate;
        }

        return $rates;
    }
}
