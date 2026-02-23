<?php

namespace App\Events;

use App\Models\Auction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Auction $auction,
        public string  $reason = '',
    ) {}
}
