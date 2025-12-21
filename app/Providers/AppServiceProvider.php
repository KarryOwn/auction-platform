<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\BiddingStrategy;
use App\Services\Bidding\PessimisticSqlEngine;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(BiddingStrategy::class, PessimisticSqlEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
