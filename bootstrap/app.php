<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
    App\Console\Commands\StressTest::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff'      => \App\Http\Middleware\EnsureIsStaff::class,
            'admin'      => \App\Http\Middleware\EnsureIsAdmin::class,
            'not_banned' => \App\Http\Middleware\EnsureNotBanned::class,
            'seller'     => \App\Http\Middleware\EnsureIsSeller::class,
            'track.auction.view' => \App\Http\Middleware\TrackAuctionView::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureNotBanned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
