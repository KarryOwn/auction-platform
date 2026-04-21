<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->id === $this->user_id;
        $isStaff = $request->user()?->isStaff() ?? false;

        return [
            'id'                    => $this->id,
            'title'                 => $this->title,
            'current_price'         => (float) $this->current_price,
            'reserve_met'           => (bool) $this->reserve_met,
            'reserve_price'         => ($this->reserve_price_visible || $isOwner || $isStaff)
                                        ? (float) $this->reserve_price
                                        : null,
            'reserve_price_visible' => (bool) $this->reserve_price_visible,
            'has_reserve'           => $this->hasReserve(),
            'buy_it_now_price'      => (float) $this->buy_it_now_price,
            'buy_it_now_enabled'    => (bool) $this->buy_it_now_enabled,
            'is_buy_it_now_available' => $this->isBuyItNowAvailable(),
            'next_minimum'          => (float) $this->minimumNextBid(),
            'bid_count'             => (int) ($this->bids_count ?? $this->bid_count ?? 0),
            'highest_bidder_name'   => $this->highestBid?->user?->name,
        ];
    }
}
