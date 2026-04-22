<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
