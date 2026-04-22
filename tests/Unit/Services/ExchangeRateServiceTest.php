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
