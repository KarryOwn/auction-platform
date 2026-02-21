<?php

namespace App\Providers;

use App\Contracts\BiddingStrategy;
use App\Events\BidPlaced;
use App\Listeners\HandleBidPlaced;
use App\Models\Auction;
use App\Policies\AuctionPolicy;
use App\Services\Bidding\BidRateLimiter;
use App\Services\Bidding\PessimisticSqlEngine;
use App\Services\Bidding\RedisAtomicEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Auction::class, AuctionPolicy::class);
    }
}
