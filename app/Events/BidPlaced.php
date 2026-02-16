<?php

namespace App\Events;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int    $auctionId;
    public int    $bidderId;
    public string $bidderName;
    public float  $amount;
    public float  $previousAmount;
    public string $bidType;
    public bool   $isSnipeBid;
    public int    $bidCount;

    public function __construct(
        public Bid     $bid,
        public Auction $auction,
    ) {
        $this->auctionId      = $auction->id;
        $this->bidderId       = $bid->user_id;
        $this->bidderName     = $bid->user?->name ?? 'Unknown';
        $this->amount         = (float) $bid->amount;
        $this->previousAmount = (float) ($bid->previous_amount ?? 0);
        $this->bidType        = $bid->bid_type ?? 'manual';
        $this->isSnipeBid     = (bool) $bid->is_snipe_bid;
        $this->bidCount       = (int) $auction->bid_count;
    }

    /**
     * Broadcast on the auction's public channel.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("auctions.{$this->auctionId}"),
        ];
    }

    /**
     * Broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    /**
     * Data broadcast to the client.
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id'      => $this->auctionId,
            'bidder_id'       => $this->bidderId,
            'bidder_name'     => $this->bidderName,
            'amount'          => $this->amount,
            'previous_amount' => $this->previousAmount,
            'bid_type'        => $this->bidType,
            'is_snipe_bid'    => $this->isSnipeBid,
            'bid_count'       => $this->bidCount,
            'end_time'        => $this->auction->end_time->toIso8601String(),
        ];
    }
}
