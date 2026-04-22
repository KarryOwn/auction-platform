<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'children_count' => (int) ($this->children_count ?? 0),
            'auction_count' => (int) ($this->auctions_count ?? 0),
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn ($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'slug' => $child->slug,
            ])->values()),
            'breadcrumb' => $this->when(
                $this->relationLoaded('parent') || ($this->path !== null),
                fn () => $this->breadcrumb->map(fn ($crumb) => [
                    'id' => $crumb->id,
                    'name' => $crumb->name,
                    'slug' => $crumb->slug,
                ])->values()
            ),
            'links' => [
                'self' => route('api.v1.categories.show', $this->resource),
            ],
        ];
    }
}
