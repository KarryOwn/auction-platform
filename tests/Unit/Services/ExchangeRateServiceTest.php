<?php

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;

test('exchange rate service returns one for same currency', function () {
    $service = app(ExchangeRateService::class);

    expect($service->getRate('USD', 'USD'))->toBe(1.0);
});

test('exchange rate service converts amounts using stored rates', function () {
    Cache::flush();

    ExchangeRate::create([
        'base_currency' => 'USD',
        'target_currency' => 'EUR',
        'rate' => 0.5,
        'fetched_at' => now(),
    ]);

    $service = app(ExchangeRateService::class);
    $converted = $service->convert(100, 'USD', 'EUR');

    expect($converted)->toBe(50.0);
});

test('exchange rate service uses configured fallback rates when db rates are unavailable', function () {
    Cache::flush();

    config(['services.exchange_rate.fallback_rates' => [
        'EUR' => 0.75,
    ]]);

    $service = app(ExchangeRateService::class);

    expect($service->getRate('USD', 'EUR'))->toBe(0.75);
    expect($service->convert(100, 'USD', 'EUR'))->toBe(75.0);
});
