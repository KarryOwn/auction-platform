<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Auction;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttributePricePredictionService
{
    private const MIN_SAMPLE_SIZE = 5;
    private const LOOKBACK_DAYS   = 180;
    private const MAX_COMPARABLES = 500;
    private const K_NEIGHBOURS    = 20;

    private const TYPE_WEIGHT = [
        'number'  => 1.0,
        'select'  => 0.9,
        'boolean' => 0.7,
        'text'    => 0.4,
    ];

    public function predict(
        int     $categoryId,
        array   $inputAttrs = [],
        ?int    $brandId    = null,
        ?string $condition  = null,
    ): array {
        $cacheKey = 'price_predict:' . md5(serialize([$categoryId, $inputAttrs, $brandId, $condition]));

        return Cache::remember($cacheKey, 300, function () use ($categoryId, $inputAttrs, $brandId, $condition) {
            return $this->computePrediction($categoryId, $inputAttrs, $brandId, $condition);
        });
    }

    private function computePrediction(int $categoryId, array $inputAttrs, ?int $brandId, ?string $condition): array
    {
        $attrDefinitions = Attribute::with('categories')->get()->keyBy('slug');
        $normalizedInput = $this->normalizeInput($inputAttrs, $attrDefinitions);
        $categoryIds     = $this->getCategoryTree($categoryId);
        $comparables     = $this->fetchComparables($categoryIds);

        $fallback = false;
        if ($comparables->count() < self::MIN_SAMPLE_SIZE) {
            $comparables = $this->fetchComparables(null);
            $fallback    = true;
        }

        if ($comparables->isEmpty()) {
            return $this->emptyPrediction();
        }

        $scored         = $this->scoreComparables($comparables, $normalizedInput, $attrDefinitions);
        $topK           = $scored->sortByDesc('similarity')->take(self::K_NEIGHBOURS);
        $predictedPrice = $this->weightedMedian($topK);

        $brandPremiumPct = 0.0;
        if ($brandId) {
            $brandPremiumPct = $this->calculateBrandPremium($brandId, $categoryIds);
            $predictedPrice  = round($predictedPrice * (1 + $brandPremiumPct / 100), 2);
        }

        $conditionAdjPct = $this->conditionAdjustment($condition);
        $predictedPrice  = round($predictedPrice * (1 + $conditionAdjPct / 100), 2);

        $prices    = $topK->pluck('price')->values();
        $stdDev    = $this->standardDeviation($prices, $predictedPrice);
        $lowEst    = max(0.01, round($predictedPrice - $stdDev * 1.2, 2));
        $highEst   = round($predictedPrice + $stdDev * 1.2, 2);

        $confidence   = $this->confidenceLabel($topK->count(), $fallback, $normalizedInput);
        $attrInsights = $this->attributeInsights($normalizedInput, $attrDefinitions, $categoryIds);

        return [
            'predicted_price'            => $predictedPrice,
            'low_estimate'               => $lowEst,
            'high_estimate'              => $highEst,
            'confidence'                 => $confidence,
            'sample_size'                => $comparables->count(),
            'comparables_used'           => $topK->count(),
            'brand_premium_pct'          => round($brandPremiumPct, 2),
            'condition_adjustment_pct'   => round($conditionAdjPct, 2),
            'attribute_insights'         => $attrInsights,
            'suggested_starting_price'   => round($predictedPrice * 0.65, 2),
            'suggested_reserve_price'    => round($predictedPrice * 0.87, 2),
            'fallback'                   => $fallback,
        ];
    }

    private function scoreComparables(Collection $comparables, array $normalizedInput, Collection $attrDefinitions): Collection
    {
        return $comparables->map(function (object $row) use ($normalizedInput, $attrDefinitions) {
            $auctionAttrs = is_string($row->attr_json)
                ? (json_decode($row->attr_json, true) ?? [])
                : [];

            $similarity = $this->computeSimilarity($normalizedInput, $auctionAttrs, $attrDefinitions);

            return [
                'auction_id' => $row->auction_id,
                'price'      => (float) $row->winning_bid_amount,
                'similarity' => $similarity,
            ];
        })->filter(fn ($row) => $row['similarity'] > 0.0);
    }

    private function computeSimilarity(array $inputAttrs, array $auctionAttrs, Collection $attrDefinitions): float
    {
        if (empty($inputAttrs)) {
            return 0.5;
        }

        $totalWeight = 0.0;
        $matchScore  = 0.0;

        foreach ($inputAttrs as $slug => $inputValue) {
            $def         = $attrDefinitions[$slug] ?? null;
            if (! $def) continue;

            $typeWeight  = self::TYPE_WEIGHT[$def->type] ?? 0.5;
            $totalWeight += $typeWeight;

            if (! isset($auctionAttrs[$slug])) {
                continue;
            }

            $matchScore += $typeWeight * $this->attributeMatch($def->type, $inputValue, $auctionAttrs[$slug], $def);
        }

        return $totalWeight === 0.0 ? 0.5 : $matchScore / $totalWeight;
    }

    private function attributeMatch(string $type, string $a, string $b, $def): float
    {
        if ($type === 'number') {
            $fa  = (float) $a;
            $fb  = (float) $b;
            $max = max(abs($fa), abs($fb));
            if ($max === 0.0) return 1.0;
            return max(0.0, 1 - abs($fa - $fb) / $max);
        }

        if ($type === 'boolean') {
            return $a === $b ? 1.0 : 0.5;
        }

        return strtolower(trim($a)) === strtolower(trim($b)) ? 1.0 : 0.0;
    }

    private function attributeInsights(array $inputAttrs, Collection $attrDefs, array $categoryIds): array
    {
        if (empty($inputAttrs) || empty($categoryIds)) {
            return [];
        }

        $insights = [];

        foreach ($inputAttrs as $slug => $value) {
            $attr = $attrDefs[$slug] ?? null;
            if (! $attr) continue;

            $withValue    = $this->avgPriceForAttrValue($attr->id, $value, $categoryIds);
            $withoutValue = $this->avgPriceForAttrAbsent($attr->id, $value, $categoryIds);

            if ($withValue === null || $withoutValue === null) {
                continue;
            }

            $impactPct = $withoutValue > 0
                ? round((($withValue - $withoutValue) / $withoutValue) * 100, 1)
                : 0.0;

            $insights[] = [
                'attribute'          => $attr->name,
                'slug'               => $slug,
                'your_value'         => $value,
                'avg_price_with'     => round($withValue, 2),
                'avg_price_without'  => round($withoutValue, 2),
                'price_impact_pct'   => $impactPct,
                'direction'          => $impactPct >= 0 ? 'positive' : 'negative',
            ];
        }

        usort($insights, fn ($a, $b) => abs($b['price_impact_pct']) <=> abs($a['price_impact_pct']));

        return $insights;
    }

    private function avgPriceForAttrValue(int $attrId, string $value, array $catIds): ?float
    {
        $result = DB::table('auctions')
            ->join('auction_attribute_values as aav', 'auctions.id', '=', 'aav.auction_id')
            ->join('auction_category as ac', 'auctions.id', '=', 'ac.auction_id')
            ->where('aav.attribute_id', $attrId)
            ->whereRaw('LOWER(aav.value) = ?', [strtolower($value)])
            ->whereIn('ac.category_id', $catIds)
            ->where('auctions.status', Auction::STATUS_COMPLETED)
            ->whereNotNull('auctions.winning_bid_amount')
            ->whereDate('auctions.closed_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->avg('auctions.winning_bid_amount');

        return $result !== null ? (float) $result : null;
    }

    private function avgPriceForAttrAbsent(int $attrId, string $value, array $catIds): ?float
    {
        $result = DB::table('auctions')
            ->join('auction_category as ac', 'auctions.id', '=', 'ac.auction_id')
            ->whereIn('ac.category_id', $catIds)
            ->where('auctions.status', Auction::STATUS_COMPLETED)
            ->whereNotNull('auctions.winning_bid_amount')
            ->whereDate('auctions.closed_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->where(function ($q) use ($attrId, $value) {
                $q->whereNotExists(function ($sub) use ($attrId) {
                    $sub->from('auction_attribute_values as aav')
                        ->whereColumn('aav.auction_id', 'auctions.id')
                        ->where('aav.attribute_id', $attrId);
                })->orWhereExists(function ($sub) use ($attrId, $value) {
                    $sub->from('auction_attribute_values as aav')
                        ->whereColumn('aav.auction_id', 'auctions.id')
                        ->where('aav.attribute_id', $attrId)
                        ->whereRaw('LOWER(aav.value) != ?', [strtolower($value)]);
                });
            })
            ->avg('auctions.winning_bid_amount');

        return $result !== null ? (float) $result : null;
    }

    private function calculateBrandPremium(int $brandId, array $categoryIds): float
    {
        $cacheKey = "brand_premium:{$brandId}:" . implode(',', $categoryIds);

        return Cache::remember($cacheKey, 3600, function () use ($brandId, $categoryIds) {
            $brandAvg = DB::table('auctions')
                ->join('auction_category as ac', 'auctions.id', '=', 'ac.auction_id')
                ->where('auctions.brand_id', $brandId)
                ->whereIn('ac.category_id', $categoryIds)
                ->where('auctions.status', Auction::STATUS_COMPLETED)
                ->whereNotNull('auctions.winning_bid_amount')
                ->avg('auctions.winning_bid_amount');

            $catAvg = DB::table('auctions')
                ->join('auction_category as ac', 'auctions.id', '=', 'ac.auction_id')
                ->whereIn('ac.category_id', $categoryIds)
                ->where('auctions.status', Auction::STATUS_COMPLETED)
                ->whereNotNull('auctions.winning_bid_amount')
                ->avg('auctions.winning_bid_amount');

            if (! $brandAvg || ! $catAvg || $catAvg == 0) {
                return 0.0;
            }

            return (($brandAvg - $catAvg) / $catAvg) * 100;
        });
    }

    private function conditionAdjustment(?string $condition): float
    {
        return match ($condition) {
            'new'         =>   0.0,
            'like_new'    =>  -3.0,
            'refurbished' =>  -8.0,
            'used_good'   => -18.0,
            'used_fair'   => -30.0,
            'for_parts'   => -55.0,
            default       =>   0.0,
        };
    }

    private function fetchComparables(?array $categoryIds): Collection
    {
        $query = DB::table('auctions as a')
            ->select([
                'a.id as auction_id',
                'a.winning_bid_amount',
                DB::raw(
                    "json_object_agg(COALESCE(attr.slug,'_unknown'), aav.value) " .
                    "FILTER (WHERE aav.attribute_id IS NOT NULL) as attr_json"
                ),
            ])
            ->leftJoin('auction_attribute_values as aav', 'a.id', '=', 'aav.auction_id')
            ->leftJoin('attributes as attr', 'aav.attribute_id', '=', 'attr.id')
            ->where('a.status', Auction::STATUS_COMPLETED)
            ->whereNotNull('a.winning_bid_amount')
            ->whereDate('a.closed_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->groupBy('a.id', 'a.winning_bid_amount')
            ->limit(self::MAX_COMPARABLES);

        if ($categoryIds !== null) {
            $query->join('auction_category as ac', 'a.id', '=', 'ac.auction_id')
                  ->whereIn('ac.category_id', $categoryIds);
        }

        try {
            return $query->get();
        } catch (\Throwable $e) {
            Log::warning('AttributePricePrediction: query failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function getCategoryTree(int $categoryId): array
    {
        $category = Category::find($categoryId);
        if (! $category) return [$categoryId];
        return array_merge([$categoryId], $category->descendant_ids);
    }

    private function weightedMedian(Collection $scored): float
    {
        $items      = $scored->sortByDesc('similarity')->values();
        $total      = $items->sum('similarity');
        if ($total == 0) return (float) $items->median('price');

        $cumulative = 0.0;
        $half       = $total / 2;

        foreach ($items as $item) {
            $cumulative += $item['similarity'];
            if ($cumulative >= $half) {
                return round((float) $item['price'], 2);
            }
        }

        return round((float) $items->last()['price'], 2);
    }

    private function standardDeviation(Collection $prices, float $mean): float
    {
        $count = $prices->count();
        if ($count < 2) return $mean * 0.10;

        $variance = $prices->reduce(fn ($carry, $p) => $carry + (($p - $mean) ** 2), 0.0) / ($count - 1);
        return sqrt($variance);
    }

    private function normalizeInput(array $input, Collection $attrDefs): array
    {
        $normalized = [];
        foreach ($input as $key => $value) {
            if ($value === null || $value === '') continue;
            if (is_numeric($key)) {
                $def = $attrDefs->firstWhere('id', (int) $key);
                if ($def) $normalized[$def->slug] = (string) $value;
            } else {
                $normalized[(string) $key] = (string) $value;
            }
        }
        return $normalized;
    }

    private function confidenceLabel(int $k, bool $fallback, array $input): string
    {
        if ($fallback)     return 'low';
        if ($k < 5)        return 'low';
        if (empty($input)) return 'low';
        if ($k < 10)       return 'medium';
        return 'high';
    }

    private function emptyPrediction(): array
    {
        return [
            'predicted_price'          => 0.0,
            'low_estimate'             => 0.0,
            'high_estimate'            => 0.0,
            'confidence'               => 'none',
            'sample_size'              => 0,
            'comparables_used'         => 0,
            'brand_premium_pct'        => 0.0,
            'condition_adjustment_pct' => 0.0,
            'attribute_insights'       => [],
            'suggested_starting_price' => 0.0,
            'suggested_reserve_price'  => 0.0,
            'fallback'                 => true,
        ];
    }
}
