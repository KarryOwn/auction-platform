<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const ROOT_COUNTS_CACHE_KEY = 'categories:root_with_counts:v2';

    /**
     * Get the full nested category tree.
     */
    public function getTree(bool $activeOnly = true): Collection
    {
        $cacheKey = 'categories:tree:' . ($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($activeOnly) {
            return Category::buildTree(null, $activeOnly);
        });
    }

    /**
     * Get a flat list of categories (optionally under a parent).
     */
    public function getFlatList(?int $parentId = null, bool $activeOnly = true): Collection
    {
        $query = Category::query()->ordered();

        if ($parentId !== null) {
            $query->where('parent_id', $parentId);
        }

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get root categories with auction counts.
     */
    public function getRootWithAuctionCounts(): Collection
    {
        return Cache::remember(self::ROOT_COUNTS_CACHE_KEY, self::CACHE_TTL, function () {
            $categories = Category::root()
                ->active()
                ->ordered()
                ->get();

            return $this->attachLiveAuctionCounts($categories);
        });
    }

    /**
     * Get categories with auction counts (for a parent).
     */
    public function getWithAuctionCounts(?int $parentId = null): Collection
    {
        $categories = Category::query()
            ->where('parent_id', $parentId)
            ->active()
            ->ordered()
            ->get();

        return $this->attachLiveAuctionCounts($categories);
    }

    /**
     * Attach live auction counts for each category, including descendant categories.
     */
    private function attachLiveAuctionCounts(Collection $categories): Collection
    {
        foreach ($categories as $category) {
            $categoryIds = array_merge([$category->id], $category->descendant_ids);

            $count = Auction::query()
                ->active()
                ->whereHas('categories', function (Builder $query) use ($categoryIds) {
                    $query->whereIn('categories.id', $categoryIds);
                })
                ->count();

            $category->setAttribute('auctions_count', $count);
        }

        return $categories;
    }

    /**
     * Get all attributes for given category IDs (including ancestor attributes).
     */
    public function getAttributesForCategory(int $categoryId): Collection
    {
        $cacheKey = "categories:{$categoryId}:attributes";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($categoryId) {
            $category = Category::find($categoryId);

            if (! $category) {
                return new Collection();
            }

            return $category->getAllAttributes();
        });
    }

    /**
     * Get attributes for multiple categories (deduplicated).
     */
    public function getAttributesForCategories(array $categoryIds): Collection
    {
        $allAttributes = new Collection();

        foreach ($categoryIds as $categoryId) {
            $attrs = $this->getAttributesForCategory($categoryId);
            $allAttributes = $allAttributes->merge($attrs);
        }

        return $allAttributes->unique('id')->sortBy('sort_order')->values();
    }

    /**
     * Build a flat list for select dropdowns with indentation.
     */
    public function getNestedSelectOptions(bool $activeOnly = true): array
    {
        $result = [];
        $this->buildFlatOptions(Category::buildTree(null, $activeOnly), $result, 0);

        return $result;
    }

    private function buildFlatOptions(Collection $categories, array &$result, int $level): void
    {
        foreach ($categories as $category) {
            $prefix = str_repeat('— ', $level);
            $result[$category->id] = $prefix . $category->name;

            if ($category->relationLoaded('children') && $category->children->isNotEmpty()) {
                $this->buildFlatOptions($category->children, $result, $level + 1);
            }
        }
    }

    /**
     * Invalidate all category-related caches.
     */
    public function invalidateCache(): void
    {
        Cache::forget('categories:tree:active');
        Cache::forget('categories:tree:all');
        Cache::forget('categories:root_with_counts');
        Cache::forget(self::ROOT_COUNTS_CACHE_KEY);

        // Clear attribute caches for all categories
        Category::all()->each(function (Category $category) {
            Cache::forget("categories:{$category->id}:attributes");
        });
    }
}
