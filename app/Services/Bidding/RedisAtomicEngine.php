<?php

namespace App\Services\Bidding;

use App\Contracts\BiddingStrategy;
use App\Events\BidPlaced;
use App\Events\PriceUpdated;
use App\Exceptions\BidValidationException;
use App\Jobs\ProcessWinningBid;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Services\EscrowService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisAtomicEngine implements BiddingStrategy
{
    /**
     * Lua script: atomically check price + min increment, then update.
     * Returns: new_price (success) or 0 (fail).
     */
    private const LUA_BID = <<<'LUA'
        local current_price  = tonumber(redis.call('get', KEYS[1]) or "0")
        local bid_amount     = tonumber(ARGV[1])
        local min_increment  = tonumber(ARGV[2])
        local current_cents  = math.floor((current_price * 100) + 0.5)
        local bid_cents      = math.floor((bid_amount * 100) + 0.5)
        local increment_cents = math.floor((min_increment * 100) + 0.5)

        if bid_cents >= (current_cents + increment_cents) then
            local normalized_bid = bid_cents / 100
            redis.call('set', KEYS[1], string.format("%.2f", normalized_bid))
            return string.format("%.2f", normalized_bid)
        else
            return "0"
        end
    LUA;

    public function __construct(
        protected BidValidator $validator,
        protected BidRateLimiter $rateLimiter,
        protected EscrowService $escrowService,
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

        // Initialize Redis price if not present
        if (! Redis::exists($priceKey)) {
            Redis::set($priceKey, (string) $auction->current_price);
        }

        $previousPrice = (float) Redis::get($priceKey);

        // Atomic Lua check-and-set
        $result = Redis::eval(self::LUA_BID, 1, $priceKey, (string) $amount, (string) $minIncrement);

        if ($result === '0' || $result === 0) {
            // Bid rejected — roll back the escrow to previous level
            $this->rollbackEscrow($user, $auction, $holdBefore);

            $current    = (float) Redis::get($priceKey);
            $minimumBid = round($current + $minIncrement, 2);
            throw BidValidationException::bidTooLow($current, $minimumBid);
        }

        $isSnipeBid = $auction->isInSnipeWindow();

        // Build a Bid model instance for the immediate response
        $bid = new Bid([
            'auction_id'      => $auction->id,
            'user_id'         => $user->id,
            'amount'          => $amount,
            'bid_type'        => $meta['bid_type'] ?? Bid::TYPE_MANUAL,
            'previous_amount' => $previousPrice,
            'ip_address'      => $meta['ip_address'] ?? request()->ip(),
            'user_agent'      => $meta['user_agent'] ?? request()->userAgent(),
            'auto_bid_id'     => $meta['auto_bid_id'] ?? null,
            'is_snipe_bid'    => $isSnipeBid,
        ]);

        // Update in-memory model for broadcast
        $auction->current_price = $amount;

        // Record rate-limit hit
        $this->rateLimiter->hit($user, $auction);

        // Broadcast real-time price update (immediate)
        try {
            PriceUpdated::dispatch($auction);
        } catch (\Throwable $e) {
            Log::error('PriceUpdated broadcast failed', ['error' => $e->getMessage()]);
        }

        // Queue the persistent write + event dispatch
        ProcessWinningBid::dispatch(
            auctionId: $auction->id,
            userId: $user->id,
            amount: $amount,
            meta: array_merge($meta, [
                'previous_amount' => $previousPrice,
                'ip_address'      => $bid->ip_address,
                'user_agent'      => $bid->user_agent,
                'is_snipe_bid'    => $isSnipeBid,
            ]),
        )->onQueue('bids');

        return $bid;
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
        ]);
    }
}