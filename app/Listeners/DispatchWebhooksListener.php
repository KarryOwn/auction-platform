<?php

namespace App\Listeners;

use App\Events\BidPlaced;
use App\Events\AuctionClosed;
use App\Events\AuctionCancelled;
use App\Services\WebhookDispatchService;

class DispatchWebhooksListener
{
    public function __construct(protected WebhookDispatchService $service) {}

    public function handleBidPlaced(BidPlaced $event): void
    {
        $this->service->dispatch('bid.placed', [
            'auction_id'  => $event->auctionId,
            'bid_id'      => $event->bid->id,
            'amount'      => $event->amount,
            'bidder_id'   => $event->bidderId,
            'created_at'  => now()->toIso8601String(),
        ], $event->auction->user_id); // notify seller's webhooks
    }

    public function handleAuctionClosed(AuctionClosed $event): void
    {
        $this->service->dispatch('auction.closed', $event->broadcastWith(), $event->auction->user_id);
    }

    public function handleAuctionCancelled(AuctionCancelled $event): void
    {
        $this->service->dispatch('auction.cancelled', [
            'auction_id' => $event->auction->id,
            'title'      => $event->auction->title,
            'reason'     => $event->reason,
        ], $event->auction->user_id);
    }
}
