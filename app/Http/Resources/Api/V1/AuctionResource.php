<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @OA\Schema(
 *     schema="AuctionResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="current_price", type="number"),
 *     @OA\Property(property="starting_price", type="number"),
 *     @OA\Property(property="reserve_met", type="boolean"),
 *     @OA\Property(property="reserve_price", type="number", nullable=true),
 *     @OA\Property(property="min_bid_increment", type="number"),
 *     @OA\Property(property="next_minimum_bid", type="number"),
 *     @OA\Property(property="bid_count", type="integer"),
 *     @OA\Property(property="currency", type="string"),
 *     @OA\Property(property="condition", type="string"),
 *     @OA\Property(property="condition_label", type="string"),
 *     @OA\Property(property="end_time", type="string", format="date-time"),
 *     @OA\Property(property="time_remaining", type="string"),
 *     @OA\Property(property="seller", type="object"),
 *     @OA\Property(property="brand", type="object", nullable=true),
 *     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="images", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="links", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="AuctionCollection",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AuctionResource")),
 *     @OA\Property(property="links", type="object"),
 *     @OA\Property(property="meta", type="object")
 * )
 */
class AuctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->id === $this->user_id;
        $isStaff = $request->user()?->isStaff() ?? false;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'current_price' => (float) $this->current_price,
            'starting_price' => (float) $this->starting_price,
            'reserve_met' => (bool) $this->reserve_met,
            'reserve_price' => ($this->reserve_price_visible || $isOwner || $isStaff)
                ? (float) $this->reserve_price
                : null,
            'min_bid_increment' => (float) $this->min_bid_increment,
            'next_minimum_bid' => (float) $this->minimumNextBid(),
            'bid_count' => (int) ($this->bids_count ?? $this->bid_count ?? 0),
            'currency' => $this->currency,
            'condition' => $this->condition,
            'condition_label' => $this->condition_label,
            'end_time' => $this->end_time?->toIso8601String(),
            'time_remaining' => $this->timeRemaining(),
            'seller' => [
                'id' => $this->user_id,
                'name' => $this->seller?->name,
                'seller_slug' => $this->seller?->seller_slug,
            ],
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ]),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_primary' => (bool) ($category->pivot->is_primary ?? false),
            ])->values()),
            'images' => $this->getMedia('images')->map(fn (Media $media) => [
                'thumbnail' => $media->getUrl('thumbnail'),
                'gallery' => $media->getUrl('gallery'),
                'full' => $media->getUrl('full'),
            ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'links' => [
                'self' => route('api.v1.auctions.show', $this->resource),
            ],
        ];
    }
}
