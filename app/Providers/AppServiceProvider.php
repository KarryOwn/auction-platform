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

        // Bidding engine binding (swap to PessimisticSqlEngine for non-Redis setups)
        // $this->app->bind(BiddingStrategy::class, PessimisticSqlEngine::class);
        $this->app->bind(BiddingStrategy::class, RedisAtomicEngine::class);

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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
