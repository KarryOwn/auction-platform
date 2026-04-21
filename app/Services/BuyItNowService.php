<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\User;
use App\Models\Invoice;
use App\Events\AuctionClosed;
use App\Contracts\BiddingStrategy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyItNowService
{
    public function __construct(
        protected EscrowService $escrowService,
        protected PaymentService $paymentService,
        protected BiddingStrategy $biddingStrategy,
    ) {}

    /**
     * Atomically process a Buy It Now purchase.
     * Closes the auction immediately, sets winner, captures payment.
     */
    public function purchase(Auction $auction, User $buyer): Invoice
    {
        if (! $auction->isBuyItNowAvailable()) {
            throw new \DomainException('Buy It Now is no longer available for this auction.');
        }

        if ($auction->user_id === $buyer->id) {
            throw new \DomainException('You cannot buy your own auction.');
        }

        $price = (float) $auction->buy_it_now_price;

        if (! $buyer->canAfford($price)) {
            throw new \DomainException('Insufficient wallet balance.');
        }

        return DB::transaction(function () use ($auction, $buyer, $price) {
            // Re-lock
            $locked = Auction::lockForUpdate()->findOrFail($auction->id);

            if ($locked->status !== Auction::STATUS_ACTIVE || ! $locked->isBuyItNowAvailable()) {
                throw new \DomainException('Buy It Now is no longer available.');
            }

            // Hold funds
            $this->escrowService->holdForBid($buyer, $locked, $price);

            // Close auction
            $locked->status              = Auction::STATUS_COMPLETED;
            $locked->winner_id           = $buyer->id;
            $locked->winning_bid_amount  = $price;
            $locked->current_price       = $price;
            $locked->win_method          = 'buy_it_now';
            $locked->closed_at           = now();
            $locked->buy_it_now_enabled  = false;
            $locked->save();

            // Capture payment immediately
            $invoice = $this->paymentService->captureWinnerPayment($locked);

            Log::info('BuyItNowService: purchase completed', [
                'auction_id' => $locked->id,
                'buyer_id'   => $buyer->id,
                'price'      => $price,
                'invoice_id' => $invoice->id,
            ]);

            AuctionClosed::dispatch($locked->fresh());

            $this->biddingStrategy->cleanup($locked);

            return $invoice;
        });
    }
}
