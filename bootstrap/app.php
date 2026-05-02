<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        App\Console\Commands\SeedStressBots::class,
        App\Console\Commands\MigrateSqliteToPgsql::class,
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
            \App\Http\Middleware\CaptureReferralCode::class,
            \App\Http\Middleware\EnsureNotBanned::class,
            \App\Http\Middleware\SetUserLocale::class,
            \App\Http\Middleware\SetDisplayCurrency::class,
            \App\Http\Middleware\MaintenanceAnnouncement::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return $e->render($request);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'Unauthenticated.',
                    'code' => 401,
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'Validation failed.',
                    'code' => 422,
                    'details' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => $e->getMessage() !== '' ? $e->getMessage() : 'HTTP error.',
                    'code' => $e->getStatusCode(),
                ], $e->getStatusCode());
            }
        });
    })->create();
