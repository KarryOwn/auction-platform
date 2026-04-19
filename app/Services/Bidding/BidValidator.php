<?php

namespace App\Services\Bidding;

use App\Exceptions\BidValidationException;
use App\Models\Auction;
use App\Models\EscrowHold;
use App\Models\User;

class BidValidator
{
    /**
     * Run all validation rules before a bid is accepted.
     *
     * @throws BidValidationException
     */
    public function validate(Auction $auction, User $user, float $amount): void
    {
        $this->ensureUserNotBanned($user);
        $this->ensureAuctionActive($auction);
        $this->ensureAuctionNotEnded($auction);
        $this->ensureNotSelfBid($auction, $user);
        $this->ensureBidHighEnough($auction, $amount);
        $this->ensureSufficientFunds($user, $auction, $amount);
    }

    protected function ensureUserNotBanned(User $user): void
    {
        if ($user->isBanned()) {
            throw BidValidationException::userBanned();
        }
    }

    protected function ensureAuctionActive(Auction $auction): void
    {
        if ($auction->status !== Auction::STATUS_ACTIVE) {
            throw BidValidationException::auctionNotActive();
        }
    }

    protected function ensureAuctionNotEnded(Auction $auction): void
    {
        if ($auction->end_time->isPast()) {
            throw BidValidationException::auctionEnded();
        }
    }

    protected function ensureNotSelfBid(Auction $auction, User $user): void
    {
        if ($auction->user_id === $user->id) {
            throw BidValidationException::selfBid();
        }
    }

    protected function ensureBidHighEnough(Auction $auction, float $amount): void
    {
        $amount = round($amount, 2);
        $minimumBid = $auction->minimumNextBid();
        $amountCents = (int) round($amount * 100);
        $minimumBidCents = (int) round($minimumBid * 100);

        if ($amountCents < $minimumBidCents) {
            throw BidValidationException::bidTooLow(
                (float) $auction->current_price,
                $minimumBid,
            );
        }
    }

    /**
     * Ensure the user has sufficient available balance for the incremental hold.
     * If the user already has an active hold on this auction, only the difference
     * between the new bid and the existing hold is required.
     */
    protected function ensureSufficientFunds(User $user, Auction $auction, float $amount): void
    {
        $existingHold = EscrowHold::where('user_id', $user->id)
            ->where('auction_id', $auction->id)
            ->where('status', EscrowHold::STATUS_ACTIVE)
            ->value('amount');

        $existingAmount  = $existingHold ? (float) $existingHold : 0.0;
        $incrementalCost = round($amount - $existingAmount, 2);

        // If incrementalCost <= 0, user already has enough held (raising bid within existing hold)
        if ($incrementalCost > 0 && ! $user->canAfford($incrementalCost)) {
            throw BidValidationException::insufficientFunds(
                required:  $incrementalCost,
                available: $user->availableBalance(),
            );
        }
    }
}
