<?php

namespace App\Events;

use App\Models\Auction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int     $auctionId;
    public ?int    $winnerId;
    public ?float  $winningAmount;
    public float   $finalPrice;
    public bool    $reserveMet;
    public int     $totalBids;
    public string  $closedAt;

    public function __construct(public Auction $auction)
    {
        $this->auctionId     = $auction->id;
        $this->winnerId      = $auction->winner_id;
        $this->winningAmount = $auction->winning_bid_amount ? (float) $auction->winning_bid_amount : null;
        $this->finalPrice    = (float) $auction->current_price;
        $this->reserveMet    = (bool) $auction->reserve_met;
        $this->totalBids     = (int) $auction->bid_count;
        $this->closedAt      = $auction->closed_at?->toIso8601String() ?? now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("auctions.{$this->auctionId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'auction.closed';
    }

    public function broadcastWith(): array
    {
        return [
            'auction_id'     => $this->auctionId,
            'winner_id'      => $this->winnerId,
            'winning_amount' => $this->winningAmount,
            'final_price'    => $this->finalPrice,
            'reserve_met'    => $this->reserveMet,
            'total_bids'     => $this->totalBids,
            'closed_at'      => $this->closedAt,
        ];
    }
}
