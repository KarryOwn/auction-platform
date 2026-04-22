<?php

use App\Exceptions\BidValidationException;
use App\Models\Auction;
use App\Models\BidRateLimit;
use App\Models\User;
use App\Services\Bidding\BidRateLimiter;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('rate limiter falls back to database when redis is unavailable', function () {
    $user = User::factory()->create();
    $auction = Auction::factory()->create();

    Redis::shouldReceive('zadd')->andThrow(new RuntimeException('redis down'));

    $limiter = app(BidRateLimiter::class);
    $limiter->hit($user, $auction);

    $record = BidRateLimit::where('user_id', $user->id)
        ->where('auction_id', $auction->id)
        ->first();

    expect($record)->not->toBeNull();
    expect($record->bid_count)->toBe(1);
});

test('rate limiter throws when db fallback limit is exceeded', function () {
    $user = User::factory()->create();
    $auction = Auction::factory()->create();

    Redis::shouldReceive('zremrangebyscore')->andThrow(new RuntimeException('redis down'));

    BidRateLimit::create([
        'user_id' => $user->id,
        'auction_id' => $auction->id,
        'bid_count' => 10,
        'window_start' => now()->subSeconds(10),
        'window_end' => now()->addSeconds(50),
        'last_bid_at' => now(),
        'is_throttled' => false,
    ]);

    $limiter = app(BidRateLimiter::class);

    expect(fn () => $limiter->check($user, $auction))
        ->toThrow(BidValidationException::class, 'You are bidding too fast.');
});
