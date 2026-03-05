<?php

namespace App\Listeners;

use App\Events\BidPlaced;
use App\Events\NewBidOnListing;
use App\Jobs\ProcessAutoBids;
use App\Models\AuctionWatcher;
use App\Models\Bid;
use App\Notifications\OutbidNotification;
use App\Services\EscrowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handles the BidPlaced event:
 *
 * 1. Releases escrow holds from the previous highest bidder
 * 2. Sends outbid notifications to the previous highest bidder
 * 3. Notifies watchers who opted in to outbid alerts
 * 4. Triggers auto-bid processing for competing auto-bid rules
 */
class HandleBidPlaced implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;

    public function __construct(
        protected EscrowService $escrowService,
    ) {}

    public function handle(BidPlaced $event): void
    {
        $bid     = $event->bid;
        $auction = $event->auction;

        // Always broadcast price update for real-time UI
        broadcast(new NewBidOnListing($auction, (float) $bid->amount))->toOthers();

        // Release escrow for the previous highest bidder (regardless of bid type)
        $this->releaseOutbidEscrow($bid, $auction);

        // Skip outbid/watcher notifications for auto-bids — they fire rapidly
        // in sequence and would spam the user. Only the initial manual bid
        // that triggers the auto-bid chain should create notifications.
        if ($bid->bid_type !== Bid::TYPE_MANUAL) {
            return;
        }

        // 1. Outbid notification to the previous highest bidder
        $outbidUserId = $this->notifyOutbidUser($bid, $auction);

        // 2. Notify watchers (skip the bidder AND the already-notified outbid user)
        $this->notifyWatchers($bid, $auction, $outbidUserId);

        // 3. Trigger auto-bid processing
        ProcessAutoBids::dispatch($auction->id, $bid->user_id)->onQueue('bids');
    }

    /**
     * Release escrow hold for the previous highest bidder when outbid.
     */
    protected function releaseOutbidEscrow(Bid $bid, $auction): void
    {
        // Find the previous highest bid by a different user
        $previousBid = Bid::where('auction_id', $auction->id)
            ->where('user_id', '!=', $bid->user_id)
            ->where('id', '!=', $bid->id)
            ->orderByDesc('amount')
            ->first();

        if (! $previousBid) {
            return;
        }

        try {
            $previousUser = $previousBid->user;
            if ($previousUser) {
                $this->escrowService->releaseForUser($previousUser, $auction);
            }
        } catch (\Throwable $e) {
            Log::error('HandleBidPlaced: escrow release failed for outbid user', [
                'outbid_user_id' => $previousBid->user_id,
                'auction_id'     => $auction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find the previous highest bidder and notify them they've been outbid.
     * Returns the outbid user's ID so we can skip them in watcher notifications.
     */
    protected function notifyOutbidUser(Bid $bid, $auction): ?int
    {
        // Find the previous highest bid by a different user
        $previousBid = Bid::where('auction_id', $auction->id)
            ->where('user_id', '!=', $bid->user_id)
            ->where('id', '!=', $bid->id)
            ->orderByDesc('amount')
            ->with('user')
            ->first();

        if (! $previousBid || ! $previousBid->user) {
            return null;
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

        return $previousBid->user_id;
    }

    /**
     * Notify auction watchers who have outbid notifications enabled.
     */
    protected function notifyWatchers(Bid $bid, $auction, ?int $outbidUserId = null): void
    {
        $excludeIds = array_filter([$bid->user_id, $outbidUserId]);

        $watchers = AuctionWatcher::where('auction_id', $auction->id)
            ->whereNotIn('user_id', $excludeIds)
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
