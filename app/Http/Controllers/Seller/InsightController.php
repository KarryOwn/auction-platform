<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Category;
use App\Services\AttributePricePredictionService;
use App\Services\PriceSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(
        private readonly PriceSuggestionService          $suggestionService,
        private readonly AttributePricePredictionService $predictionService,
    ) {}

    // Existing — keyword-based suggestion (unchanged)
    public function suggestPrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $data = $this->suggestionService->suggest(
            $validated['title'],
            $validated['description'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    // NEW — attribute-aware price prediction
    public function predict(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id'  => ['required', 'integer', 'exists:categories,id'],
            'brand_id'     => ['nullable', 'integer', 'exists:brands,id'],
            'condition'    => ['nullable', 'string', 'in:new,like_new,used_good,used_fair,refurbished,for_parts'],
            'attributes'   => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->predictionService->predict(
            categoryId: (int) $validated['category_id'],
            inputAttrs: $validated['attributes'] ?? [],
            brandId:    isset($validated['brand_id']) ? (int) $validated['brand_id'] : null,
            condition:  $validated['condition'] ?? null,
        );

        return response()->json(['data' => $result]);
    }

    // NEW — returns attributes for a category so the frontend can build the form
    public function categoryAttributes(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $category   = Category::findOrFail($request->integer('category_id'));
        $attributes = $category->getAllAttributes()->map(fn ($attr) => [
            'id'            => $attr->id,
            'name'          => $attr->name,
            'slug'          => $attr->slug,
            'type'          => $attr->type,
            'unit'          => $attr->unit,
            'options'       => $attr->options,
            'is_filterable' => $attr->is_filterable,
            'is_required'   => $attr->is_required,
            'sort_order'    => $attr->sort_order,
        ]);

        return response()->json(['data' => $attributes]);
    }

    // Existing — live auction health insights (unchanged)
    public function auctionInsights(Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);

        $insights = $this->suggestionService->auctionInsights($auction);

        return view('seller.insights.show', compact('auction', 'insights'));
    }
}
