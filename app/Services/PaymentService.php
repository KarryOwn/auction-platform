<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        protected EscrowService $escrowService,
        protected WalletService $walletService,
        protected InvoiceService $invoiceService,
    ) {}

    /**
     * Capture payment from the winner's held funds and credit the seller.
     */
    public function captureWinnerPayment(Auction $auction): Invoice
    {
        $winner = User::findOrFail($auction->winner_id);
        $seller = User::findOrFail($auction->user_id);
        $amount = (float) $auction->winning_bid_amount;

        return DB::transaction(function () use ($auction, $winner, $seller, $amount) {
            // 1. Capture the winner's escrow hold
            $this->escrowService->captureForWinner($winner, $auction);

            // 2. Calculate platform fee and seller payout
            $platformFee  = $this->calculatePlatformFee($amount);
            $sellerAmount = round($amount - $platformFee, 2);

            // 3. Credit seller wallet
            $this->walletService->creditSeller(
                $seller,
                $sellerAmount,
                "Auction #{$auction->id} payout: {$auction->title}",
                $auction,
            );

            // 4. Update auction payment status
            $auction->update(['payment_status' => 'paid']);

            // 5. Generate invoice
            $invoice = $this->invoiceService->generateForAuction($auction, $platformFee, $sellerAmount);

            Log::info('PaymentService: winner payment captured', [
                'auction_id'    => $auction->id,
                'winner_id'     => $winner->id,
                'seller_id'     => $seller->id,
                'total'         => $amount,
                'platform_fee'  => $platformFee,
                'seller_amount' => $sellerAmount,
                'invoice_id'    => $invoice->id,
            ]);

            return $invoice;
        });
    }

    /**
     * Calculate the platform fee for a given amount.
     */
    public function calculatePlatformFee(float $amount): float
    {
        $feePercent = (float) config('auction.platform_fee_percent', 5.0);

        return round($amount * ($feePercent / 100), 2);
    }

    /**
     * Calculate the seller payout amount after platform fee.
     */
    public function calculateSellerAmount(float $amount): float
    {
        return round($amount - $this->calculatePlatformFee($amount), 2);
    }
}
