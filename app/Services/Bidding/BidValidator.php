<?php

namespace App\Services\Bidding;

use App\Exceptions\BidValidationException;
use App\Models\Auction;
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
        $minimumBid = $auction->minimumNextBid();

        if ($amount < $minimumBid) {
            throw BidValidationException::bidTooLow(
                (float) $auction->current_price,
                $minimumBid,
            );
        }
    }
}
