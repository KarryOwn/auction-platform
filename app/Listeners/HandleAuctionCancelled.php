<?php

namespace App\Listeners;

use App\Events\AuctionCancelled;
use App\Models\AuctionWatcher;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\AuctionCancelledNotification;
use App\Services\EscrowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handles the AuctionCancelled event:
 *
 * 1. Releases all escrow holds for bidders
 * 2. Notifies watchers who opted in to cancellation alerts
 * 3. Notifies all unique bidders
 * 4. Deduplicates so no user gets two notifications
 */
class HandleAuctionCancelled implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;

    public function __construct(
        protected EscrowService $escrowService,
    ) {}

    public function handle(AuctionCancelled $event): void
    {
        $auction = $event->auction;
        $reason  = $event->reason;

        // Release all escrow holds for this auction
        try {
            $this->escrowService->releaseAllForAuction($auction);
            Log::info('HandleAuctionCancelled: all escrow holds released', [
                'auction_id' => $auction->id,
            ]);
        } catch (\Throwable $e) {
            Log::critical('HandleAuctionCancelled: escrow release FAILED', [
                'auction_id' => $auction->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // Collect watcher user IDs where notify_cancelled = true
        $watcherUserIds = AuctionWatcher::where('auction_id', $auction->id)
            ->where('notify_cancelled', true)
            ->pluck('user_id');

        // Collect all unique bidder user IDs
        $bidderUserIds = Bid::where('auction_id', $auction->id)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        // Merge and deduplicate — track who is a bidder for message context
        $bidderSet  = $bidderUserIds->flip();
        $allUserIds = $watcherUserIds->merge($bidderUserIds)->unique();

        if ($allUserIds->isEmpty()) {
            return;
        }

        $users = User::whereIn('id', $allUserIds)->get();

        foreach ($users as $user) {
            try {
                $user->notify(new AuctionCancelledNotification(
                    auctionId:    $auction->id,
                    auctionTitle: $auction->title,
                    reason:       $reason,
                    isBidder:     $bidderSet->has($user->id),
                ));
            } catch (\Throwable $e) {
                Log::warning('HandleAuctionCancelled: notification failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('HandleAuctionCancelled: notifications sent', [
            'auction_id' => $auction->id,
            'count'      => $allUserIds->count(),
        ]);
    }
}
