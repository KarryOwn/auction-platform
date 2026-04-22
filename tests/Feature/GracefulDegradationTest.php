<?php

namespace Tests\Feature;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\User;
use App\Services\Bidding\BidRateLimiter;
use App\Services\Bidding\PessimisticSqlEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class GracefulDegradationTest extends TestCase
{
    use RefreshDatabase;

    public function test_binds_pessimistic_sql_engine_when_redis_down()
    {
        Notification::fake();
        config(['auction.engine' => 'redis']);
        
        // Mock Redis::connection()->ping() to throw exception
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('ping')->andThrow(new \Exception('Connection refused'));

        // Resolve from container
        $engine = app(BiddingStrategy::class);

        $this->assertInstanceOf(PessimisticSqlEngine::class, $engine);
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            \App\Notifications\RedisDownNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === config('auction.ops_email');
            }
        );
    }

    public function test_bid_rate_limiter_falls_back_to_db()
    {
        // Mock Redis completely out
        Redis::shouldReceive('zremrangebyscore')->andThrow(new \Exception('Redis down'));
        Redis::shouldReceive('zcard')->andThrow(new \Exception('Redis down'));
        Redis::shouldReceive('zadd')->andThrow(new \Exception('Redis down'));
        Redis::shouldReceive('expire')->andThrow(new \Exception('Redis down'));

        $user = User::factory()->create();
        $auction = Auction::factory()->create();

        $limiter = new BidRateLimiter(maxBids: 2, windowSeconds: 60);

        // First hit (should fall back to DB and save record)
        $limiter->hit($user, $auction);
        $this->assertDatabaseHas('bid_rate_limits', [
            'user_id' => $user->id,
            'auction_id' => $auction->id,
            'bid_count' => 1,
        ]);

        // Second hit
        $limiter->hit($user, $auction);
        $this->assertDatabaseHas('bid_rate_limits', [
            'user_id' => $user->id,
            'auction_id' => $auction->id,
            'bid_count' => 2,
        ]);

        // Now checking should throw rate limited exception using DB rate limiter
        $this->expectException(\App\Exceptions\BidValidationException::class);
        $limiter->check($user, $auction);
    }
}
