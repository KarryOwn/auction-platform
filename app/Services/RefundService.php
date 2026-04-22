<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\EscrowHold;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

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
            $invoice = Invoice::where('auction_id', $auction->id)->first();

            // Use stored invoice values when available so historical refunds
            // are not affected by later commission-rate changes.
            if ($invoice) {
                $platformFee = (float) $invoice->platform_fee;
                $sellerAmount = (float) $invoice->seller_amount;
            } else {
                $platformFee = round($amount * (float) config('auction.platform_fee_percent', 0.05), 2);
                $sellerAmount = round($amount - $platformFee, 2);
            }

            // 1. Issue Stripe refund first if applicable
            $tx = WalletTransaction::where('user_id', $buyer->id)
                ->where('type', WalletTransaction::TYPE_PAYMENT)
                ->whereNotNull('stripe_payment_intent_id')
                ->where('reference_type', 'App\Models\Auction')
                ->where('reference_id', $auction->id)
                ->first();

            if ($tx?->stripe_payment_intent_id) {
                try {
                    \Stripe\Refund::create([
                        'payment_intent' => $tx->stripe_payment_intent_id,
                        'amount'         => (int) round($amount * 100), // cents
                        'reason'         => 'requested_by_customer',
                        'metadata'       => [
                            'auction_id' => $auction->id,
                            'reason'     => $reason,
                        ],
                    ]);
                    Log::info('RefundService: Stripe refund issued', [
                        'auction_id'         => $auction->id,
                        'payment_intent'     => $tx->stripe_payment_intent_id,
                        'amount'             => $amount,
                    ]);
                } catch (ApiErrorException $e) {
                    Log::critical('RefundService: Stripe refund failed', [
                        'auction_id' => $auction->id,
                        'error'      => $e->getMessage(),
                    ]);
                    throw $e; // bubble up — do NOT credit wallet if Stripe fails
                }
            }

            // 2. Refund buyer
            $this->walletService->refund(
                $buyer,
                $amount,
                "Refund for auction #{$auction->id}: {$reason}",
                $auction,
            );

            // 3. Claw back seller credit
            $this->walletService->withdraw(
                $seller,
                $sellerAmount,
                "Payout reversed for auction #{$auction->id}: {$reason}",
            );

            // 4. Update auction payment status
            $auction->update(['payment_status' => 'refunded']);

            // 5. Update invoice status
            if ($invoice) {
                $invoice->markRefunded();
            }

            // 6. Mark escrow hold as refunded
            $hold = EscrowHold::where('user_id', $buyer->id)
                ->where('auction_id', $auction->id)
                ->where('status', EscrowHold::STATUS_CAPTURED)
                ->first();
            if ($hold) {
                $hold->markRefunded();
            }

            // 7. Notify buyer
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
