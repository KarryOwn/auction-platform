<?php

namespace App\Http\Controllers;

use App\Contracts\BiddingStrategy;
use App\Models\Attribute;
use App\Models\Auction;
use App\Models\AuctionAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionComparisonController extends Controller
{
    public function __construct(protected BiddingStrategy $engine) {}

    public function compare(Request $request)
    {
        if ($request->isMethod('get') && ! $request->expectsJson() && ! $request->ajax()) {
            $ids = collect((array) $request->input('ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->take(4)
                ->values()
                ->all();

            $comparison = count($ids) >= 2 ? $this->buildComparisonPayload($ids) : null;

            return view('auctions.compare', [
                'initialIds' => $ids,
                'comparison' => $comparison,
            ]);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:2', 'max:4'],
            'ids.*' => ['integer', 'exists:auctions,id'],
        ]);

        return response()->json($this->buildComparisonPayload($validated['ids']));
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<string, mixed>
     */
    protected function buildComparisonPayload(array $ids): array
    {
        $orderedIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $auctions = Auction::query()
            ->whereIn('id', $orderedIds)
            ->where('status', Auction::STATUS_ACTIVE)
            ->where('end_time', '>', now())
            ->with([
                'brand',
                'categories',
                'media',
                'primaryCategory',
                'attributeValues.attribute',
            ])
            ->withCount('bids')
            ->get()
            ->sortBy(fn (Auction $auction) => $orderedIds->search($auction->id))
            ->values();

        $allAttributeSlugs = $auctions
            ->flatMap(fn (Auction $auction) => $auction->attributeValues->map(fn ($attributeValue) => [
                'slug' => $attributeValue->attribute->slug,
                'name' => $attributeValue->attribute->name,
            ]))
            ->unique('slug')
            ->values();

        $rows = $auctions->map(function (Auction $auction) use ($allAttributeSlugs) {
            $currentPrice = $this->engine->getCurrentPrice($auction);
            $attributeMap = $auction->attributeValues->keyBy(fn ($attributeValue) => $attributeValue->attribute->slug);

            return [
                'id' => $auction->id,
                'title' => $auction->title,
                'current_price' => $currentPrice,
                'next_minimum' => round($currentPrice + (float) $auction->min_bid_increment, 2),
                'bid_count' => (int) $auction->bids_count,
                'time_remaining' => $auction->timeRemaining(),
                'end_time' => $auction->end_time->toIso8601String(),
                'condition' => $auction->condition_label,
                'brand' => $auction->brand?->name,
                'category' => $auction->primaryCategory->first()?->name ?? $auction->categories->first()?->name,
                'reserve_met' => (bool) $auction->reserve_met,
                'thumbnail_url' => $auction->getCoverImageUrl('thumbnail'),
                'url' => route('auctions.show', $auction),
                'live_state_url' => route('auctions.live-state', $auction),
                'attributes' => $allAttributeSlugs->mapWithKeys(fn (array $attribute) => [
                    $attribute['slug'] => $this->formatAttributeValue($attributeMap[$attribute['slug']] ?? null),
                ])->all(),
            ];
        });

        return [
            'attribute_columns' => $allAttributeSlugs->all(),
            'auctions' => $rows->all(),
        ];
    }

    protected function formatAttributeValue(?AuctionAttributeValue $attributeValue): ?string
    {
        $value = $attributeValue?->value;

        if ($value === null || $value === '') {
            return null;
        }

        if ($attributeValue?->attribute?->type === Attribute::TYPE_BOOLEAN && in_array($value, ['1', 1, true, 'true'], true)) {
            return 'Yes';
        }

        if ($attributeValue?->attribute?->type === Attribute::TYPE_BOOLEAN && in_array($value, ['0', 0, false, 'false'], true)) {
            return 'No';
        }

        return (string) $value;
    }
}
