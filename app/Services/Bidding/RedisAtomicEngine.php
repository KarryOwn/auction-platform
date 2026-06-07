<?php

namespace App\Services\Bidding;

use App\Contracts\BiddingStrategy;
use App\Events\PriceUpdated;
use App\Exceptions\BidValidationException;
use App\Jobs\BatchPersistRedisBids;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Services\EscrowService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisAtomicEngine implements BiddingStrategy
{
    /**
     * Lua script: atomically initialize price, check price + min increment, update price,
     * and record the accepted bid for durable queue recovery.
     * Returns: {new_price, previous_price, drain_scheduled} (success) or {"0", current_price, "0"} (fail).
     */
    private const LUA_BID = <<<'LUA'
        local stored_price = redis.call('get', KEYS[1])
        local current_price  = tonumber(stored_price or ARGV[12] or "0")
        local bid_amount     = tonumber(ARGV[1])
        local min_increment  = tonumber(ARGV[2])
        local current_cents  = math.floor((current_price * 100) + 0.5)
        local bid_cents      = math.floor((bid_amount * 100) + 0.5)
        local increment_cents = math.floor((min_increment * 100) + 0.5)

        if not stored_price then
            redis.call('set', KEYS[1], string.format("%.2f", current_price))
        end

        if bid_cents >= (current_cents + increment_cents) then
            local normalized_bid = bid_cents / 100
            redis.call('set', KEYS[1], string.format("%.2f", normalized_bid))

            local payload = {
                auction_id = tonumber(ARGV[4]),
                user_id = tonumber(ARGV[5]),
                amount = normalized_bid,
                bid_type = ARGV[6],
                previous_amount = current_price,
                ip_address = ARGV[7],
                user_agent = ARGV[8],
                auto_bid_id = ARGV[9],
                is_snipe_bid = ARGV[10] == "1",
                accepted_at = tonumber(ARGV[11])
            }

            redis.call('hset', KEYS[2], ARGV[3], cjson.encode(payload))
            redis.call('zadd', KEYS[3], ARGV[11], ARGV[3])
            redis.call('zadd', KEYS[4], ARGV[11], ARGV[4])

            local drain_scheduled = "0"
            if ARGV[14] == "1" then
                local ttl_seconds = tonumber(ARGV[13])
                local scheduled = redis.call('set', KEYS[5], "1", "NX", "EX", ttl_seconds)
                if scheduled then
                    drain_scheduled = "1"
                end
            end

            return { string.format("%.2f", normalized_bid), tostring(current_price), drain_scheduled }
        else
            return { "0", tostring(current_price), "0" }
        end
    LUA;

    public function __construct(
        protected BidValidator $validator,
        protected BidRateLimiter $rateLimiter,
        protected EscrowService $escrowService,
        protected BiddingEngineHealth $health,
    ) {}

    public function placeBid(Auction $auction, User $user, float $amount, array $meta = []): Bid
    {
        $amount = round($amount, 2);

        // Pre-flight validation (includes wallet balance check)
        $this->validator->validate($auction, $user, $amount);
        $this->rateLimiter->check($user, $auction);

        // Hold funds BEFORE attempting the atomic price update.
        // If the Lua CAS fails, we release the incremental hold.
        $holdBefore = $this->escrowService->getActiveHoldAmount($user, $auction);
        $escrowHold = $this->escrowService->holdForBid($user, $auction, $amount);

        $priceKey     = "auction:{$auction->id}:price";
        $minIncrement = (float) $auction->min_bid_increment;

        $acceptedBidId = (string) Str::ulid();
        $acceptedAt = microtime(true);
        $bidType = $meta['bid_type'] ?? Bid::TYPE_MANUAL;
        $ipAddress = $meta['ip_address'] ?? request()->ip();
        $userAgent = $meta['user_agent'] ?? request()->userAgent();
        $autoBidId = $meta['auto_bid_id'] ?? null;
        $isSnipeBid = $auction->isInSnipeWindow();
        $dispatchPriceUpdate = ! (bool) ($meta['stress_suppress_price_broadcast'] ?? false);
        $dispatchPersistence = ! (bool) ($meta['stress_suppress_persistence_dispatch'] ?? false);

        try {
            // Atomic Lua check-and-set. The script initializes a missing price key.
            $result = Redis::eval(
                self::LUA_BID,
                5,
                $priceKey,
                PendingRedisBidStore::hashKey($auction->id),
                PendingRedisBidStore::indexKey($auction->id),
                PendingRedisBidStore::GLOBAL_INDEX_KEY,
                PendingRedisBidStore::drainScheduledKey($auction->id),
                (string) $amount,
                (string) $minIncrement,
                $acceptedBidId,
                (string) $auction->id,
                (string) $user->id,
                $bidType,
                (string) $ipAddress,
                (string) $userAgent,
                $autoBidId ? (string) $autoBidId : '',
                $isSnipeBid ? '1' : '0',
                (string) $acceptedAt,
                (string) $auction->current_price,
                (string) max(1, (int) config('auction.redis_persistence.dispatch_window_seconds', 1)),
                $dispatchPersistence ? '1' : '0',
            );
        } catch (\Throwable $e) {
            $this->health->markRedisDegraded($e->getMessage());
            $this->rollbackEscrow($user, $auction, $holdBefore);

            Log::warning('RedisAtomicEngine: Redis unavailable during bid, falling back to SQL', [
                'auction_id' => $auction->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return app(PessimisticSqlEngine::class)->placeBid($auction->fresh(), $user, $amount, $meta);
        }

        $acceptedAmount = $this->luaValue($result, 0);
        $previousPrice = (float) $this->luaValue($result, 1);
        $drainScheduled = (string) $this->luaValue($result, 2) === '1';

        if ($acceptedAmount === '0' || $acceptedAmount === 0 || $acceptedAmount === null) {
            // Bid rejected — roll back the escrow to previous level
            $this->rollbackEscrow($user, $auction, $holdBefore);

            $current    = (float) Redis::get($priceKey);
            $minimumBid = round($current + $minIncrement, 2);
            throw BidValidationException::bidTooLow($current, $minimumBid);
        }

        // Build a Bid model instance for the immediate response
        $bid = new Bid([
            'accepted_bid_id' => $acceptedBidId,
            'auction_id'      => $auction->id,
            'user_id'         => $user->id,
            'amount'          => $amount,
            'bid_type'        => $bidType,
            'previous_amount' => $previousPrice,
            'ip_address'      => $ipAddress,
            'user_agent'      => $userAgent,
            'auto_bid_id'     => $autoBidId,
            'is_snipe_bid'    => $isSnipeBid,
        ]);

        // Update in-memory model for broadcast
        $auction->current_price = $amount;

        // Record rate-limit hit
        $this->rateLimiter->hit($user, $auction);

        // Broadcast real-time price update (immediate)
        if ($dispatchPriceUpdate && $this->shouldDispatchPriceUpdate($auction->id)) {
            try {
                PriceUpdated::dispatch($auction);
            } catch (\Throwable $e) {
                Log::error('PriceUpdated broadcast failed', ['error' => $e->getMessage()]);
            }
        }

        if ($drainScheduled) {
            try {
                // Queue a per-auction drainer; the pending Redis store carries the durable payload.
                BatchPersistRedisBids::dispatch(
                    auctionId: $auction->id,
                    limit: (int) config('auction.redis_persistence.batch_size', 100),
                )
                    ->onConnection((string) config('auction.bids_queue.connection', 'redis'))
                    ->onQueue('bids');
            } catch (\Throwable $e) {
                Log::warning('RedisAtomicEngine: batch persistence dispatch failed; reconciler can recover pending bid', [
                    'auction_id' => $auction->id,
                    'accepted_bid_id' => $acceptedBidId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $bid;
    }

    protected function luaValue(mixed $result, int $index): mixed
    {
        if (! is_array($result)) {
            return $index === 0 ? $result : null;
        }

        return $result[$index] ?? $result[$index + 1] ?? null;
    }

    protected function shouldDispatchPriceUpdate(int $auctionId): bool
    {
        $debounceMs = max(0, (int) config('auction.redis_persistence.price_broadcast_debounce_ms', 250));

        if ($debounceMs === 0) {
            return true;
        }

        try {
            return (bool) Redis::set(
                "auction:{$auctionId}:price_broadcast_scheduled",
                '1',
                'PX',
                $debounceMs,
                'NX',
            );
        } catch (\Throwable $e) {
            Log::warning('RedisAtomicEngine: price broadcast debounce failed open', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * Roll back escrow to the previous hold level after a failed bid.
     */
    protected function rollbackEscrow(User $user, Auction $auction, float $previousHoldAmount): void
    {
        try {
            if ($previousHoldAmount > 0) {
                // Restore to previous hold amount
                $this->escrowService->holdForBid($user, $auction, $previousHoldAmount);
            } else {
                // No previous hold — release entirely
                $this->escrowService->releaseForUser($user, $auction);
            }
        } catch (\Throwable $e) {
            Log::error('RedisAtomicEngine: escrow rollback failed', [
                'user_id'    => $user->id,
                'auction_id' => $auction->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function getCurrentPrice(Auction $auction): float
    {
        $key = "auction:{$auction->id}:price";

        if (Redis::exists($key)) {
            return (float) Redis::get($key);
        }

        return (float) $auction->current_price;
    }

    public function initializePrice(Auction $auction): void
    {
        Redis::set("auction:{$auction->id}:price", (string) $auction->current_price);
    }

    public function cleanup(Auction $auction): void
    {
        Redis::del([
            "auction:{$auction->id}:price",
            "auction:{$auction->id}:meta",
            "auction:{$auction->id}:leaderboard",
            "auction:{$auction->id}:price_broadcast_scheduled",
            "auction:{$auction->id}:seller_bid_broadcast_scheduled",
            PendingRedisBidStore::hashKey($auction->id),
            PendingRedisBidStore::indexKey($auction->id),
            PendingRedisBidStore::drainScheduledKey($auction->id),
        ]);
        Redis::zrem(PendingRedisBidStore::GLOBAL_INDEX_KEY, (string) $auction->id);
    }
}
