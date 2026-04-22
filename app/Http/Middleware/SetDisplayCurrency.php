<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDisplayCurrency
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_map('strtoupper', config('auction.supported_currencies', ['USD']));
        $currency = strtoupper(
            $request->user()?->userPreference?->display_currency
                ?? $request->cookie('display_currency')
                ?? 'USD'
        );

        if (! in_array($currency, $supported, true)) {
            $currency = 'USD';
        }

        app()->instance('display_currency', $currency);

        return $next($request);
    }
}
