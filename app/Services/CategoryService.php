<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    private const CACHE_TTL = 3600; // 1 hour

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
        return Cache::remember('categories:root_with_counts', self::CACHE_TTL, function () {
            return Category::root()
                ->active()
                ->ordered()
                ->withCount(['auctions' => function (Builder $q) {
                    $q->where('status', 'active')->where('end_time', '>', now());
                }])
                ->get();
        });
    }

    /**
     * Get categories with auction counts (for a parent).
     */
    public function getWithAuctionCounts(?int $parentId = null): Collection
    {
        return Category::query()
            ->where('parent_id', $parentId)
            ->active()
            ->ordered()
            ->withCount(['auctions' => function (Builder $q) {
                $q->where('status', 'active')->where('end_time', '>', now());
            }])
            ->get();
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

        // Clear attribute caches for all categories
        Category::all()->each(function (Category $category) {
            Cache::forget("categories:{$category->id}:attributes");
        });
    }
}
