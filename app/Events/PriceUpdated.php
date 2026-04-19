<?php

namespace App\Events;

use App\Models\Auction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PriceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $auctionId;
    public float $newPrice;
    public float $nextMinimum;

    /**
     * Create a new event instance.
     */
    public function __construct(Auction $auction)
    {
        $this->auctionId = (int) $auction->id;
        $this->newPrice = (float) $auction->current_price;
        $this->nextMinimum = (float) $auction->minimumNextBid();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('auctions.'.$this->auctionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'price-updated';
    }

    /**
     * Data broadcast to the client.
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'new_price' => $this->newPrice,
            'newPrice' => $this->newPrice,
            'next_minimum' => $this->nextMinimum,
        ];
    }
}
