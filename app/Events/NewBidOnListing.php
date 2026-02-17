<?php

namespace App\Events;

use App\Models\Auction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewBidOnListing implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Auction $auction, public float $amount) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('seller.'.$this->auction->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'new.bid.on.listing';
    }

    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auction->id,
            'title' => $this->auction->title,
            'amount' => $this->amount,
            'bid_count' => $this->auction->bid_count,
        ];
    }
}
