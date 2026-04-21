<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\ListingFeeTier;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListingFeeService
{
    public function __construct(protected WalletService $walletService) {}

    /**
     * Calculate the listing fee for an auction before charging.
     */
    public function calculate(Auction $auction): float
    {
        // Check config-level global fee first
        $globalFlat    = (float) config('auction.listing_fee.flat', 0.0);
        $globalPercent = (float) config('auction.listing_fee.percent', 0.0);

        // Look for a matching tier (most specific wins)
        $tier = $this->resolveTier($auction);

        if ($tier) {
            $flat    = (float) $tier->fee_amount;
            $percent = (float) $tier->fee_percent;
        } else {
            $flat    = $globalFlat;
            $percent = $globalPercent;
        }

        $startingPrice = (float) $auction->starting_price;
        $fee = $flat + ($startingPrice * $percent);

        return round($fee, 2);
    }

    /**
     * Charge the listing fee by debiting the seller's wallet.
     * Called inside AuctionCrudController::publish() transaction.
     */
    public function charge(Auction $auction, User $seller): WalletTransaction
    {
        $fee = $this->calculate($auction);

        if ($fee <= 0) {
            return new WalletTransaction(); // No fee — return empty transaction
        }

        if (! $seller->canAfford($fee)) {
            throw new \DomainException(
                "Insufficient wallet balance to pay the listing fee of \${$fee}. "
                . "Available: \$" . $seller->availableBalance()
            );
        }

        $tx = $this->walletService->withdraw(
            $seller,
            $fee,
            "Listing fee for auction: {$auction->title}",
        );

        $auction->update([
            'listing_fee_charged' => $fee,
            'listing_fee_paid'    => true,
        ]);

        Log::info('ListingFeeService: fee charged', [
            'auction_id' => $auction->id,
            'seller_id'  => $seller->id,
            'fee'        => $fee,
        ]);

        return $tx;
    }

    private function resolveTier(Auction $auction): ?ListingFeeTier
    {
        $categoryId    = $auction->categories()->wherePivot('is_primary', true)->value('categories.id');
        $startingPrice = (float) $auction->starting_price;

        return ListingFeeTier::where('is_active', true)
            ->where(function ($q) use ($categoryId) {
                $q->whereNull('category_id')->orWhere('category_id', $categoryId);
            })
            ->where(function ($q) use ($startingPrice) {
                $q->whereNull('starting_price_min')->orWhere('starting_price_min', '<=', $startingPrice);
            })
            ->where(function ($q) use ($startingPrice) {
                $q->whereNull('starting_price_max')->orWhere('starting_price_max', '>', $startingPrice);
            })
            ->orderByDesc('category_id') // category-specific tier wins over global tier
            ->orderBy('sort_order')
            ->first();
    }
}
