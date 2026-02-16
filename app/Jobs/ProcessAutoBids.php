<?php

namespace App\Jobs;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Triggered after a manual bid is placed.
 * Checks if any other users have auto-bid rules that should fire
 * and places bids on their behalf.
 *
 * Prevents infinite loops by only triggering the top qualifying auto-bid
 * that is not the current highest bidder.
 */
class ProcessAutoBids implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [1, 3];

    public function __construct(
        public int $auctionId,
        public int $triggeredByUserId,
    ) {}

    public function handle(BiddingStrategy $engine): void
    {
        $auction = Auction::find($this->auctionId);

        if (! $auction || ! $auction->isActive()) {
            return;
        }

        $currentPrice = $engine->getCurrentPrice($auction);
        $nextBid      = round($currentPrice + (float) $auction->min_bid_increment, 2);

        // Find auto-bids that:
        // 1. Belong to a different user than the one who just bid
        // 2. Have max_amount >= nextBid
        // 3. Ordered by max_amount desc (highest ceiling wins)
        $autoBid = AutoBid::where('auction_id', $this->auctionId)
            ->where('user_id', '!=', $this->triggeredByUserId)
            ->where('max_amount', '>=', $nextBid)
            ->orderByDesc('max_amount')
            ->with('user')
            ->first();

        if (! $autoBid || ! $autoBid->user) {
            return;
        }

        // Safety: don't auto-bid if userBanned
        if ($autoBid->user->isBanned()) {
            Log::info('ProcessAutoBids: skipping banned user', ['user_id' => $autoBid->user_id]);
            return;
        }

        try {
            $engine->placeBid($auction->fresh(), $autoBid->user, $nextBid, [
                'bid_type'    => Bid::TYPE_AUTO,
                'auto_bid_id' => $autoBid->id,
                'ip_address'  => '127.0.0.1',
                'user_agent'  => 'AutoBid/System',
            ]);

            $autoBid->markTriggered();

            Log::info('ProcessAutoBids: auto-bid placed', [
                'auto_bid_id' => $autoBid->id,
                'auction_id'  => $this->auctionId,
                'user_id'     => $autoBid->user_id,
                'amount'      => $nextBid,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProcessAutoBids: failed to place auto-bid', [
                'auto_bid_id' => $autoBid->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
