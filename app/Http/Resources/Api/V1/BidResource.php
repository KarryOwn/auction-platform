<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'auction_id' => $this->auction_id,
            'amount' => (float) $this->amount,
            'bid_type' => $this->bid_type,
            'is_snipe_bid' => (bool) $this->is_snipe_bid,
            'created_at' => $this->created_at?->toIso8601String(),
            'auction' => $this->whenLoaded('auction', fn () => [
                'id' => $this->auction->id,
                'title' => $this->auction->title,
                'status' => $this->auction->status,
            ]),
        ];
    }
}
