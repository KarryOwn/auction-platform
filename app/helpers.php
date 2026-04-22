<?php

use App\Services\ExchangeRateService;

if (! function_exists('display_currency')) {
    function display_currency(): string
    {
        $currency = app()->bound('display_currency') ? (string) app('display_currency') : 'USD';
        $supported = array_map('strtoupper', config('auction.supported_currencies', ['USD']));

        return in_array($currency, $supported, true) ? $currency : 'USD';
    }
}

if (! function_exists('format_price')) {
    function format_price(float $amountUsd, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?? display_currency());

        if ($currency === 'USD') {
            return '$' . number_format($amountUsd, 2);
        }

        $converted = app(ExchangeRateService::class)->convert($amountUsd, 'USD', $currency);

        $symbols = [
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'VND' => '₫',
        ];

        $symbol = $symbols[$currency] ?? ($currency . ' ');
        $decimals = in_array($currency, ['JPY', 'VND'], true) ? 0 : 2;

        return $symbol . number_format($converted, $decimals);
    }
}
