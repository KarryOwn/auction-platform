<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a verified seller with wallet balance.
 */
function createSeller(array $overrides = []): \App\Models\User
{
    return \App\Models\User::factory()->create(array_merge([
        'role'                       => 'seller',
        'seller_verified_at'         => now(),
        'seller_application_status'  => 'approved',
        'wallet_balance'             => 1000,
    ], $overrides));
}

/**
 * Create an active auction owned by a seller.
 */
function createActiveAuction(\App\Models\User $seller, array $overrides = []): \App\Models\Auction
{
    return \App\Models\Auction::factory()->create(array_merge([
        'user_id'          => $seller->id,
        'status'           => \App\Models\Auction::STATUS_ACTIVE,
        'end_time'         => now()->addHour(),
        'current_price'    => 100.00,
        'starting_price'   => 100.00,
        'min_bid_increment'=> 5.00,
    ], $overrides));
}

/**
 * Bind PessimisticSqlEngine for tests that don't need Redis.
 */
function useSqlBiddingEngine(): void
{
    app()->bind(
        \App\Contracts\BiddingStrategy::class,
        \App\Services\Bidding\PessimisticSqlEngine::class,
    );
}
