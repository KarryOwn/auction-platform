<?php

namespace App\Contracts;

use App\Models\Auction;
use App\Models\User;
use App\Models\Bid;

interface BiddingStrategy
{
    /**
     * Attempt to place a bid.
     * Returns the Bid object on success, or throws an exception on failure.
     */
    public function placeBid(Auction $auction, User $user, float $amount): Bid;
}