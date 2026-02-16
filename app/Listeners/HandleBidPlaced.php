<?php

namespace App\Listeners;

use App\Events\BidPlaced;
use App\Jobs\ProcessAutoBids;
use App\Models\AuctionWatcher;
use App\Models\Bid;
use App\Notifications\OutbidNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handles the BidPlaced event:
 *
 * 1. Sends outbid notifications to the previous highest bidder
 * 2. Notifies watchers who opted in to outbid alerts
 * 3. Triggers auto-bid processing for competing auto-bid rules
 */
class HandleBidPlaced implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;

    public function handle(BidPlaced $event): void
    {
        $bid     = $event->bid;
        $auction = $event->auction;

        // 1. Outbid notification to the previous highest bidder
        $this->notifyOutbidUser($bid, $auction);

        // 2. Notify watchers
        $this->notifyWatchers($bid, $auction);

        // 3. Trigger auto-bid processing (only for manual bids to prevent loops)
        if ($bid->bid_type === Bid::TYPE_MANUAL) {
            ProcessAutoBids::dispatch($auction->id, $bid->user_id)->onQueue('bids');
        }
    }

    /**
     * Find the previous highest bidder and notify them they've been outbid.
     */
    protected function notifyOutbidUser(Bid $bid, $auction): void
    {
        // Find the previous highest bid by a different user
        $previousBid = Bid::where('auction_id', $auction->id)
            ->where('user_id', '!=', $bid->user_id)
            ->where('id', '!=', $bid->id)
            ->orderByDesc('amount')
            ->with('user')
            ->first();

        if (! $previousBid || ! $previousBid->user) {
            return;
        }

        try {
            $previousBid->user->notify(new OutbidNotification(
                auctionId:    $auction->id,
                auctionTitle: $auction->title,
                outbidAmount: (float) $bid->amount,
                yourAmount:   (float) $previousBid->amount,
            ));

            Log::info('HandleBidPlaced: outbid notification sent', [
                'outbid_user_id' => $previousBid->user_id,
                'auction_id'     => $auction->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HandleBidPlaced: outbid notification failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify auction watchers who have outbid notifications enabled.
     */
    protected function notifyWatchers(Bid $bid, $auction): void
    {
        $watchers = AuctionWatcher::where('auction_id', $auction->id)
            ->where('user_id', '!=', $bid->user_id)
            ->where('notify_outbid', true)
            ->with('user')
            ->get();

        foreach ($watchers as $watcher) {
            if (! $watcher->user) {
                continue;
            }

            try {
                $watcher->user->notify(new OutbidNotification(
                    auctionId:    $auction->id,
                    auctionTitle: $auction->title,
                    outbidAmount: (float) $bid->amount,
                    yourAmount:   0, // watchers may not have bid
                    isWatcher:    true,
                ));
            } catch (\Throwable $e) {
                Log::warning('HandleBidPlaced: watcher notification failed', [
                    'watcher_user_id' => $watcher->user_id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }
    }
}
