<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="BidResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="auction_id", type="integer"),
 *     @OA\Property(property="amount", type="number"),
 *     @OA\Property(property="bid_type", type="string"),
 *     @OA\Property(property="is_snipe_bid", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="auction", type="object", nullable=true)
 * )
 */
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
