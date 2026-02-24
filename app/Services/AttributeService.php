<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Auction;
use App\Models\AuctionAttributeValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class AttributeService
{
    /**
     * Get all attributes for given category IDs (deduplicated).
     */
    public function getForCategories(array $categoryIds): Collection
    {
        return Attribute::whereHas('categories', function ($q) use ($categoryIds) {
            $q->whereIn('categories.id', $categoryIds);
        })->orderBy('sort_order')->get();
    }

    /**
     * Validate attribute values against their definitions.
     */
    public function validateValues(array $values, array $categoryIds): array
    {
        $attributes = $this->getForCategories($categoryIds);
        $errors = [];
        $validated = [];

        foreach ($attributes as $attribute) {
            $value = $values[$attribute->id] ?? $values[$attribute->slug] ?? null;

            // Check required
            $isRequired = $attribute->is_required || $attribute->categories()
                ->whereIn('categories.id', $categoryIds)
                ->wherePivot('is_required', true)
                ->exists();

            if ($isRequired && ($value === null || $value === '')) {
                $errors["attributes.{$attribute->slug}"] = "{$attribute->name} is required.";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            // Type validation
            if (! $attribute->isValidValue($value)) {
                $errors["attributes.{$attribute->slug}"] = "{$attribute->name} has an invalid value.";
                continue;
            }

            $validated[$attribute->id] = (string) $value;
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }

    /**
     * Sync attribute values for an auction.
     */
    public function syncAuctionAttributes(Auction $auction, array $values): void
    {
        // Delete existing values
        $auction->attributeValues()->delete();

        // Insert new values
        foreach ($values as $attributeId => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            AuctionAttributeValue::create([
                'auction_id'   => $auction->id,
                'attribute_id' => $attributeId,
                'value'        => (string) $value,
            ]);
        }
    }
}
