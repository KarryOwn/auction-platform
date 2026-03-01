<?php

namespace App\Listeners;

use App\Events\AuctionClosed;
use App\Events\AuctionEndedForSeller;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\AuctionLostNotification;
use App\Notifications\AuctionWonNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handles the AuctionClosed event:
 *
 * 1. Sends AuctionWonNotification to the winner (if reserve met)
 * 2. Sends AuctionLostNotification to all other unique bidders
 */
class HandleAuctionClosed implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;

    public function handle(AuctionClosed $event): void
    {
        $auction = $event->auction;

        // No bids → nothing to notify
        if ($auction->bid_count === 0) {
            return;
        }

        $winnerId = $auction->winner_id;

        // 1. Notify the winner (reserve was met and there is a winner)
        if ($winnerId) {
            $this->notifyWinner($auction, $winnerId);
        }

        // 2. Notify the seller in real-time
        try {
            AuctionEndedForSeller::dispatch($auction);
        } catch (\Throwable $e) {
            Log::warning('HandleAuctionClosed: AuctionEndedForSeller broadcast failed', [
                'auction_id' => $auction->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // 3. Notify all other bidders that they lost
        $this->notifyLosers($auction, $winnerId);
    }

    protected function notifyWinner($auction, int $winnerId): void
    {
        $winner = User::find($winnerId);
        if (! $winner) {
            return;
        }

        try {
            $winner->notify(new AuctionWonNotification(
                auctionId:     $auction->id,
                auctionTitle:  $auction->title,
                winningAmount: (float) $auction->winning_bid_amount,
            ));

            Log::info('HandleAuctionClosed: won notification sent', [
                'user_id'    => $winnerId,
                'auction_id' => $auction->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HandleAuctionClosed: won notification failed', [
                'user_id' => $winnerId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    protected function notifyLosers($auction, ?int $winnerId): void
    {
        // Get all unique bidders except the winner
        $loserQuery = Bid::where('auction_id', $auction->id)
            ->select('user_id')
            ->distinct();

        if ($winnerId) {
            $loserQuery->where('user_id', '!=', $winnerId);
        }

        $loserIds = $loserQuery->pluck('user_id');

        if ($loserIds->isEmpty()) {
            return;
        }

        $users = User::whereIn('id', $loserIds)->get();

        foreach ($users as $user) {
            // Find this user's highest bid on the auction
            $highestBid = Bid::where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->max('amount');

            try {
                $user->notify(new AuctionLostNotification(
                    auctionId:      $auction->id,
                    auctionTitle:   $auction->title,
                    finalPrice:     (float) $auction->current_price,
                    yourHighestBid: (float) $highestBid,
                ));
            } catch (\Throwable $e) {
                Log::warning('HandleAuctionClosed: lost notification failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('HandleAuctionClosed: lost notifications sent', [
            'auction_id' => $auction->id,
            'count'      => $loserIds->count(),
        ]);
    }
}
