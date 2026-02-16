<?php

namespace App\Services\Bidding;

use App\Exceptions\BidValidationException;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

class BidRateLimiter
{
    /**
     * Maximum bids per user per auction within the window.
     */
    protected int $maxBids;

    /**
     * Window size in seconds.
     */
    protected int $windowSeconds;

    public function __construct(int $maxBids = 10, int $windowSeconds = 60)
    {
        $this->maxBids       = $maxBids;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Check whether the user is allowed to bid. Throws if rate-limited.
     *
     * Uses a Redis sliding-window counter: bid_rate:{userId}:{auctionId}
     *
     * @throws BidValidationException
     */
    public function check(User $user, Auction $auction): void
    {
        $key = "bid_rate:{$user->id}:{$auction->id}";
        $now = microtime(true);

        // Remove entries outside the window
        Redis::zremrangebyscore($key, '-inf', $now - $this->windowSeconds);

        // Count remaining entries
        $count = Redis::zcard($key);

        if ($count >= $this->maxBids) {
            // Calculate when the oldest entry expires
            $oldest = Redis::zrange($key, 0, 0);
            $retryAfter = $oldest
                ? max(1, (int) ceil(($oldest[0] + $this->windowSeconds) - $now))
                : $this->windowSeconds;

            throw BidValidationException::rateLimited($retryAfter);
        }
    }

    /**
     * Record a successful bid hit.
     */
    public function hit(User $user, Auction $auction): void
    {
        $key = "bid_rate:{$user->id}:{$auction->id}";
        $now = microtime(true);

        Redis::zadd($key, $now, "{$now}");
        Redis::expire($key, $this->windowSeconds + 5); // auto-cleanup buffer
    }

    /**
     * Get remaining allowed bids in the current window.
     */
    public function remaining(User $user, Auction $auction): int
    {
        $key = "bid_rate:{$user->id}:{$auction->id}";
        Redis::zremrangebyscore($key, '-inf', microtime(true) - $this->windowSeconds);

        return max(0, $this->maxBids - (int) Redis::zcard($key));
    }

    /**
     * Clear the rate limit for a user on an auction.
     */
    public function clear(User $user, Auction $auction): void
    {
        Redis::del("bid_rate:{$user->id}:{$auction->id}");
    }
}
