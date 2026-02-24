<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Brand;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryBrowseController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * Show all root categories.
     */
    public function index()
    {
        $categories = $this->categoryService->getRootWithAuctionCounts();

        return view('categories.index', compact('categories'));
    }

    /**
     * Show a category page with subcategories and auctions.
     */
    public function show(Request $request, Category $category)
    {
        $subcategories = $this->categoryService->getWithAuctionCounts($category->id);

        // Get all category IDs to search (this category + descendants)
        $categoryIds = array_merge([$category->id], $category->descendant_ids);

        $query = Auction::query()
            ->where('status', Auction::STATUS_ACTIVE)
            ->where('end_time', '>', now())
            ->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            })
            ->with(['media', 'brand', 'categories', 'tags'])
            ->withCount('bids');

        // Filters
        if ($minPrice = $request->input('min_price')) {
            $query->where('current_price', '>=', (float) $minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('current_price', '<=', (float) $maxPrice);
        }
        if ($condition = $request->input('condition')) {
            $query->where('condition', $condition);
        }
        if ($brandId = $request->input('brand_id')) {
            $query->where('brand_id', $brandId);
        }
        if ($q = $request->input('q')) {
            $query->where('title', 'ilike', "%{$q}%");
        }

        // Attribute filters
        if ($attrFilters = $request->input('attr')) {
            foreach ($attrFilters as $attrId => $value) {
                if (! empty($value)) {
                    $query->whereHas('attributeValues', function ($q) use ($attrId, $value) {
                        $q->where('attribute_id', $attrId)->where('value', $value);
                    });
                }
            }
        }

        // Sort
        $sort = $request->input('sort', 'ending_soon');
        $query->when($sort === 'ending_soon', fn ($q) => $q->orderBy('end_time', 'asc'))
              ->when($sort === 'newest', fn ($q) => $q->orderByDesc('created_at'))
              ->when($sort === 'price_asc', fn ($q) => $q->orderBy('current_price', 'asc'))
              ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('current_price'));

        $auctions = $query->paginate(12)->withQueryString();

        // Get filterable attributes for this category
        $filterableAttributes = $this->categoryService->getAttributesForCategory($category->id)
            ->filter(fn ($a) => $a->is_filterable);

        // Get brands that have auctions in this category
        $brands = Brand::whereHas('auctions', function ($q) use ($categoryIds) {
            $q->where('status', 'active')
              ->where('end_time', '>', now())
              ->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $categoryIds));
        })->orderBy('name')->get();

        $conditions = Auction::CONDITIONS;

        return view('categories.show', compact(
            'category',
            'subcategories',
            'auctions',
            'filterableAttributes',
            'brands',
            'conditions',
        ));
    }
}
