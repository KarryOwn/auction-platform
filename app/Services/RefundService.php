<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\EscrowHold;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundService
{
    public function __construct(
        protected WalletService $walletService,
        protected EscrowService $escrowService,
    ) {}

    /**
     * Full refund of a completed auction payment.
     * Refunds the buyer and claws back the seller credit.
     */
    public function refundAuctionPayment(Auction $auction, string $reason = 'Admin refund'): void
    {
        DB::transaction(function () use ($auction, $reason) {
            $buyer  = User::findOrFail($auction->winner_id);
            $seller = User::findOrFail($auction->user_id);
            $amount = (float) $auction->winning_bid_amount;

            // Calculate what the seller received
            $platformFeePercent = (float) config('auction.platform_fee_percent', 5.0);
            $platformFee  = round($amount * ($platformFeePercent / 100), 2);
            $sellerAmount = round($amount - $platformFee, 2);

            // 1. Refund buyer
            $this->walletService->refund(
                $buyer,
                $amount,
                "Refund for auction #{$auction->id}: {$reason}",
                $auction,
            );

            // 2. Claw back seller credit
            $this->walletService->withdraw(
                $seller,
                $sellerAmount,
                "Payout reversed for auction #{$auction->id}: {$reason}",
            );

            // 3. Update auction payment status
            $auction->update(['payment_status' => 'refunded']);

            // 4. Update invoice status
            $invoice = Invoice::where('auction_id', $auction->id)->first();
            if ($invoice) {
                $invoice->markRefunded();
            }

            // 5. Mark escrow hold as refunded
            $hold = EscrowHold::where('user_id', $buyer->id)
                ->where('auction_id', $auction->id)
                ->where('status', EscrowHold::STATUS_CAPTURED)
                ->first();
            if ($hold) {
                $hold->markRefunded();
            }

            // 6. Notify buyer
            $buyer->notify(new RefundProcessedNotification(
                auctionId:    $auction->id,
                auctionTitle: $auction->title,
                amount:       $amount,
                reason:       $reason,
            ));

            Log::info('RefundService: full refund processed', [
                'auction_id'    => $auction->id,
                'buyer_id'      => $buyer->id,
                'seller_id'     => $seller->id,
                'refund_amount' => $amount,
                'reason'        => $reason,
            ]);
        });
    }

    /**
     * Partial refund of a completed auction payment.
     */
    public function refundPartial(Auction $auction, float $refundAmount, string $reason = 'Partial refund'): void
    {
        DB::transaction(function () use ($auction, $refundAmount, $reason) {
            $buyer = User::findOrFail($auction->winner_id);

            // Refund buyer the specified amount
            $this->walletService->refund(
                $buyer,
                $refundAmount,
                "Partial refund for auction #{$auction->id}: {$reason}",
                $auction,
            );

            // Notify buyer
            $buyer->notify(new RefundProcessedNotification(
                auctionId:    $auction->id,
                auctionTitle: $auction->title,
                amount:       $refundAmount,
                reason:       $reason,
            ));

            Log::info('RefundService: partial refund processed', [
                'auction_id'    => $auction->id,
                'buyer_id'      => $buyer->id,
                'refund_amount' => $refundAmount,
                'reason'        => $reason,
            ]);
        });
    }

    /**
     * Release all escrow holds for a cancelled auction.
     * No payment to reverse since holds haven't been captured.
     */
    public function refundCancelledAuction(Auction $auction): void
    {
        $this->escrowService->releaseAllForAuction($auction);

        Log::info('RefundService: cancelled auction holds released', [
            'auction_id' => $auction->id,
        ]);
    }
}
