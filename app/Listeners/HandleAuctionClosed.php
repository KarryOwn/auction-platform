<?php

namespace App\Listeners;

use App\Events\AuctionClosed;
use App\Events\AuctionEndedForSeller;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\AuctionLostNotification;
use App\Notifications\AuctionWonNotification;
use App\Notifications\PaymentCapturedNotification;
use App\Notifications\AuctionPayoutNotification;
use App\Services\EscrowService;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handles the AuctionClosed event:
 *
 * 1. Auto-captures payment from the winner's escrow hold
 * 2. Releases escrow holds for all losing bidders
 * 3. Sends AuctionWonNotification to the winner
 * 4. Sends AuctionLostNotification to all other unique bidders
 * 5. Notifies seller of payout
 */
class HandleAuctionClosed implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;

    public function __construct(
        protected PaymentService $paymentService,
        protected EscrowService $escrowService,
    ) {}

    public function handle(AuctionClosed $event): void
    {
        $auction = $event->auction;

        // No bids → nothing to process
        if ($auction->bid_count === 0) {
            return;
        }

        $winnerId = $auction->winner_id;

        if ($winnerId) {
            // 1. Auto-capture payment from winner's escrow
            $this->capturePayment($auction, $winnerId);

            // 2. Release all other bidders' escrow holds
            $this->escrowService->releaseAllForAuction($auction, excludeUserId: $winnerId);

            // 3. Notify the winner
            $this->notifyWinner($auction, $winnerId);
        } else {
            // No winner (reserve not met) — release ALL escrow holds
            $this->escrowService->releaseAllForAuction($auction);
        }

        // 4. Notify the seller in real-time
        try {
            AuctionEndedForSeller::dispatch($auction);
        } catch (\Throwable $e) {
            Log::warning('HandleAuctionClosed: AuctionEndedForSeller broadcast failed', [
                'auction_id' => $auction->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // 5. Notify all other bidders that they lost
        $this->notifyLosers($auction, $winnerId);
    }

    protected function capturePayment($auction, int $winnerId): void
    {
        try {
            $invoice = $this->paymentService->captureWinnerPayment($auction);

            // Notify winner of payment capture
            $winner = User::find($winnerId);
            if ($winner) {
                $winner->notify(new PaymentCapturedNotification(
                    auctionId:    $auction->id,
                    auctionTitle: $auction->title,
                    amount:       (float) $auction->winning_bid_amount,
                    invoiceId:    $invoice->id,
                ));
            }

            // Notify seller of payout
            $seller = User::find($auction->user_id);
            if ($seller) {
                $sellerAmount = $this->paymentService->calculateSellerAmount(
                    (float) $auction->winning_bid_amount,
                    $auction
                );
                $seller->notify(new AuctionPayoutNotification(
                    auctionId:    $auction->id,
                    auctionTitle: $auction->title,
                    totalAmount:  (float) $auction->winning_bid_amount,
                    sellerAmount: $sellerAmount,
                    platformFee:  $this->paymentService->calculatePlatformFee(
                        (float) $auction->winning_bid_amount,
                        $auction
                    ),
                ));
            }

            Log::info('HandleAuctionClosed: payment captured', [
                'auction_id' => $auction->id,
                'winner_id'  => $winnerId,
                'invoice_id' => $invoice->id,
            ]);
        } catch (\Throwable $e) {
            // Payment capture failed — mark as failed, notify admin
            $auction->update(['payment_status' => 'failed']);

            Log::critical('HandleAuctionClosed: payment capture FAILED', [
                'auction_id' => $auction->id,
                'winner_id'  => $winnerId,
                'error'      => $e->getMessage(),
            ]);
        }
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
