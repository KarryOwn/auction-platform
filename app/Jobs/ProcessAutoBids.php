<?php

namespace App\Jobs;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAutoBids implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [1, 3];

    /**
     * Unique lock TTL (seconds) — should exceed max expected processing time.
     */
    public int $uniqueFor = 120;

    private const MAX_ROUNDS = 100;

    public function __construct(
        public int $auctionId,
        public int $triggeredByUserId,
    ) {}

    // one auto bid job per auction
    public function uniqueId(): string
    {
        return (string) $this->auctionId;
    }

    public function handle(BiddingStrategy $engine): void
    {
        $auction = Auction::find($this->auctionId);

        if (! $auction || ! $auction->isActive()) {
            return;
        }

        // Resolve the ACTUAL current highest bidder from the database,
        // not the triggeredByUserId. This prevents double-bidding on
        // job retries where an auto-bid was already placed in a prior attempt.
        $lastBidderId = $this->resolveCurrentHighestBidder($auction);

        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            // Refresh auction state for snipe-window checks, etc.
            $auction = $auction->fresh();
            if (! $auction || ! $auction->isActive()) {
                break;
            }

            $currentPrice = $engine->getCurrentPrice($auction);
            $nextBid      = round($currentPrice + (float) $auction->min_bid_increment, 2);

            // Find the best qualifying auto-bid:
            // - Different user than the current highest bidder
            // - Active
            // - Has enough budget for the next bid
            // - Highest ceiling wins ties
            $autoBid = AutoBid::where('auction_id', $this->auctionId)
                ->where('user_id', '!=', $lastBidderId)
                ->where('is_active', true)
                ->where('max_amount', '>=', $nextBid)
                ->orderByDesc('max_amount')
                ->with('user')
                ->first();

            if (! $autoBid || ! $autoBid->user) {
                break;
            }

            if ($autoBid->user->isBanned()) {
                Log::info('ProcessAutoBids: skipping banned user', ['user_id' => $autoBid->user_id]);
                break;
            }

            try {
                $engine->placeBid($auction, $autoBid->user, $nextBid, [
                    'bid_type'    => Bid::TYPE_AUTO,
                    'auto_bid_id' => $autoBid->id,
                    'ip_address'  => '127.0.0.1',
                    'user_agent'  => 'AutoBid/System',
                ]);

                $autoBid->markTriggered();
                $lastBidderId = $autoBid->user_id;

                Log::info('ProcessAutoBids: auto-bid placed', [
                    'auto_bid_id' => $autoBid->id,
                    'auction_id'  => $this->auctionId,
                    'user_id'     => $autoBid->user_id,
                    'amount'      => $nextBid,
                    'round'       => $round + 1,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ProcessAutoBids: failed to place auto-bid', [
                    'auto_bid_id' => $autoBid->id,
                    'error'       => $e->getMessage(),
                ]);
                break;
            }
        }
    }

    /**
     * Determine who actually holds the highest bid right now.
     *
     * On the first run this normally matches triggeredByUserId. On a retry
     * (after a prior attempt already placed an auto-bid), it returns the
     * user who truly leads — preventing the same auto-bidder from being
     * selected again.
     */
    private function resolveCurrentHighestBidder(Auction $auction): int
    {
        $latestBid = Bid::where('auction_id', $auction->id)
            ->orderByDesc('amount')
            ->first();

        return $latestBid ? $latestBid->user_id : $this->triggeredByUserId;
    }
}
