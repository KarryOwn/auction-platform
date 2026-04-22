<?php

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('exchange rate refresh normalizes non-usd api responses to usd base', function () {
    config()->set('services.exchange_rate.url', 'https://example.test/latest');

    Http::fake([
        'https://example.test/latest*' => Http::response([
            'base' => 'EUR',
            'rates' => [
                'USD' => 1.2,
                'EUR' => 1.0,
                'GBP' => 0.86,
                'JPY' => 165.0,
                'VND' => 30000.0,
            ],
        ]),
    ]);

    app(ExchangeRateService::class)->refresh();

    expect(round((float) ExchangeRate::where('target_currency', 'GBP')->value('rate'), 8))
        ->toBe(round(0.86 / 1.2, 8));

    expect(round((float) ExchangeRate::where('target_currency', 'VND')->value('rate'), 8))
        ->toBe(round(30000.0 / 1.2, 8));
});

test('exchange rate service reads cached recent db rates', function () {
    Cache::flush();

    ExchangeRate::create([
        'base_currency' => 'USD',
        'target_currency' => 'EUR',
        'rate' => 0.92,
        'fetched_at' => now()->subHour(),
    ]);

    $service = app(ExchangeRateService::class);

    expect($service->getRate('USD', 'EUR'))->toBe(0.92);
    expect($service->convert(100, 'USD', 'EUR'))->toBe(92.0);
});

test('exchange rate service ignores stale rates older than 24 hours', function () {
    Cache::flush();

    ExchangeRate::create([
        'base_currency' => 'USD',
        'target_currency' => 'GBP',
        'rate' => 0.8,
        'fetched_at' => now()->subHours(30),
    ]);

    $service = app(ExchangeRateService::class);

    expect($service->getRate('USD', 'GBP'))->toBe(1.0);
});
