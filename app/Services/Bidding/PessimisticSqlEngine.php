<?php

namespace App\Services\Bidding;

use App\Contracts\BiddingStrategy;
use App\Events\BidPlaced;
use App\Events\PriceUpdated;
use App\Exceptions\BidValidationException;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Services\EscrowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PessimisticSqlEngine implements BiddingStrategy
{
    public function __construct(
        protected BidValidator $validator,
        protected BidRateLimiter $rateLimiter,
        protected EscrowService $escrowService,
    ) {}

    public function placeBid(Auction $auction, User $user, float $amount, array $meta = []): Bid
    {
        $amount = round($amount, 2);

        // Pre-flight validation (before acquiring lock) — includes wallet check
        $this->validator->validate($auction, $user, $amount);
        $this->rateLimiter->check($user, $auction);

        return DB::transaction(function () use ($auction, $user, $amount, $meta) {
            // Lock the auction row — pessimistic concurrency control
            $locked = Auction::where('id', $auction->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-validate against the locked row (price may have changed)
            if ($locked->status !== Auction::STATUS_ACTIVE) {
                throw BidValidationException::auctionNotActive();
            }
            if ($locked->end_time->isPast()) {
                throw BidValidationException::auctionEnded();
            }
            $minimumBid = $locked->minimumNextBid();
            $amountCents = (int) round($amount * 100);
            $minimumBidCents = (int) round($minimumBid * 100);
            if ($amountCents < $minimumBidCents) {
                throw BidValidationException::bidTooLow((float) $locked->current_price, $minimumBid);
            }

            // Hold funds in escrow (atomic within this transaction)
            $this->escrowService->holdForBid($user, $locked, $amount);

            $previousPrice = (float) $locked->current_price;
            $isSnipeBid    = $locked->isInSnipeWindow();

            // Update auction price
            $locked->current_price = $amount;

            // Check reserve
            if ($locked->hasReserve() && ! $locked->reserve_met && $locked->isReserveMet()) {
                $locked->reserve_met = true;
            }

            $locked->save();

            // Create the bid record
            $bid = Bid::create([
                'auction_id'      => $locked->id,
                'user_id'         => $user->id,
                'amount'          => $amount,
                'bid_type'        => $meta['bid_type'] ?? Bid::TYPE_MANUAL,
                'previous_amount' => $previousPrice,
                'ip_address'      => $meta['ip_address'] ?? request()->ip(),
                'user_agent'      => $meta['user_agent'] ?? request()->userAgent(),
                'auto_bid_id'     => $meta['auto_bid_id'] ?? null,
                'is_snipe_bid'    => $isSnipeBid,
            ]);

            // Increment counters
            $locked->incrementBidCounters($user->id);

            // Anti-snipe extension
            if ($isSnipeBid) {
                $locked->applySnipeExtension();
            }

            // Record rate-limit hit
            $this->rateLimiter->hit($user, $locked);

            // Broadcast price update immediately
            try {
                PriceUpdated::dispatch($locked);
            } catch (\Throwable $e) {
                Log::error('PriceUpdated broadcast failed', ['error' => $e->getMessage()]);
            }

            // Dispatch domain event for listener chain
            BidPlaced::dispatch($bid, $locked);

            return $bid;
        });
    }

    public function getCurrentPrice(Auction $auction): float
    {
        return (float) Auction::where('id', $auction->id)->value('current_price');
    }

    public function initializePrice(Auction $auction): void
    {
        // No-op for SQL engine — price lives in the DB already.
    }

    public function cleanup(Auction $auction): void
    {
        // No-op for SQL engine.
    }
}