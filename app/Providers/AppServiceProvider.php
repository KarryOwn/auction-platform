<?php

namespace App\Providers;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Policies\AuctionPolicy;
use App\Services\Bidding\BidRateLimiter;
use App\Services\Bidding\PessimisticSqlEngine;
use App\Services\Bidding\RedisAtomicEngine;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Stripe\Stripe;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bootstrap Stripe with the secret key
        Stripe::setApiKey(config('services.stripe.secret'));

        $this->app->bind(BiddingStrategy::class, function () {
            $configured = config('auction.engine', 'redis');

            if ($configured === 'sql') {
                return app(PessimisticSqlEngine::class);
            }

            // Redis engine selected — verify Redis is reachable
            if ($this->isRedisAvailable()) {
                return app(RedisAtomicEngine::class);
            }

            // Auto-fallback to SQL engine
            \Illuminate\Support\Facades\Log::error('AppServiceProvider: Redis unavailable — falling back to PessimisticSqlEngine');

            // Alert operations team
            try {
                \Illuminate\Support\Facades\Notification::route('mail', config('auction.ops_email'))
                    ->notify(new \App\Notifications\RedisDownNotification());
            } catch (\Throwable $e) {
                // Swallow — don't let notification failure block the fallback
            }

            return app(PessimisticSqlEngine::class);
        });

        // Rate limiter as singleton (shared config across the request)
        $this->app->singleton(BidRateLimiter::class, function () {
            return new BidRateLimiter(
                maxBids: (int) config('auction.rate_limit.max_bids', 10),
                windowSeconds: (int) config('auction.rate_limit.window_seconds', 60),
            );
        });

        // Price prediction service singleton
        $this->app->singleton(\App\Services\AttributePricePredictionService::class);
    }

    private ?bool $redisAvailable = null;

    private function isRedisAvailable(): bool
    {
        if ($this->redisAvailable !== null) {
            return $this->redisAvailable;
        }

        try {
            \Illuminate\Support\Facades\Redis::connection()->ping();
            $this->redisAvailable = true;
        } catch (\Throwable $e) {
            $this->redisAvailable = false;
        }

        return $this->redisAvailable;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(\App\Events\BidPlaced::class, [\App\Listeners\DispatchWebhooksListener::class, 'handleBidPlaced']);
        \Illuminate\Support\Facades\Event::listen(\App\Events\AuctionClosed::class, [\App\Listeners\DispatchWebhooksListener::class, 'handleAuctionClosed']);
        \Illuminate\Support\Facades\Event::listen(\App\Events\AuctionCancelled::class, [\App\Listeners\DispatchWebhooksListener::class, 'handleAuctionCancelled']);

        Gate::policy(Auction::class, AuctionPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by((string) $request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api-bids', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        if (app()->isLocal()) {
            DB::listen(function ($query) {
                if (str_contains($query->sql, 'select') && $query->time > 100) {
                    Log::warning('Slow query detected', ['sql' => $query->sql, 'time' => $query->time]);
                }
            });
        }
    }
}
