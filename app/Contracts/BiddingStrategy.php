<?php

namespace App\Contracts;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;

interface BiddingStrategy
{
    /**
     * Attempt to place a bid atomically.
     *
     * @param  Auction  $auction  The auction being bid on.
     * @param  User     $user     The bidder.
     * @param  float    $amount   The bid amount.
     * @param  array    $meta     Extra context: ip_address, user_agent, bid_type, auto_bid_id.
     * @return Bid      The persisted (or pending) Bid on success.
     *
     * @throws \App\Exceptions\BidValidationException
     */
    public function placeBid(Auction $auction, User $user, float $amount, array $meta = []): Bid;

    public function getCurrentPrice(Auction $auction): float;

    public function initializePrice(Auction $auction): void;

    public function cleanup(Auction $auction): void;
}