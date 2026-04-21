<?php

namespace App\Services\Bidding;

use App\Exceptions\BidValidationException;
use App\Models\Auction;
use App\Models\User;

class BidValidator
{
    public function validate(Auction $auction, User $user, float $amount): void
    {
        $this->ensureUserCanBid($user, $auction);
        $this->ensureAuctionActive($auction);
        
        $minimumBid = $auction->minimumNextBid();
        
        if ($amount < $minimumBid) {
            throw BidValidationException::bidTooLow($auction->current_price, $minimumBid);
        }
    }

    protected function ensureUserCanBid(User $user, Auction $auction): void
    {
        if ($user->isBanned()) {
            throw BidValidationException::userBanned();
        }

        if ($user->id === $auction->user_id) {
            throw BidValidationException::selfBid();
        }
    }

    protected function ensureAuctionActive(Auction $auction): void
    {
        if ($auction->status !== Auction::STATUS_ACTIVE) {
            throw BidValidationException::auctionNotActive();
        }

        if ($auction->paused_by_vacation) {
            throw BidValidationException::auctionPaused();
        }

        if ($auction->end_time->isPast()) {
            throw BidValidationException::auctionEnded();
        }
    }
}
