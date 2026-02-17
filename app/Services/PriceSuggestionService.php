<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionSnapshot;
use Illuminate\Support\Collection;

class PriceSuggestionService
{
    public function suggest(string $title, ?string $description = null): array
    {
        $lookbackDays = (int) config('auction.insights.suggestion_lookback_days', 90);
        $minSimilar = (int) config('auction.insights.min_similar_auctions', 5);
        $startingFactor = (float) config('auction.insights.starting_price_factor', 0.65);
        $reserveFactor = (float) config('auction.insights.reserve_price_factor', 0.87);

        $keywords = collect(preg_split('/\s+/', trim($title)) ?: [])->filter(fn ($k) => mb_strlen($k) >= 3)->take(5);

        $query = Auction::query()
            ->where('status', Auction::STATUS_COMPLETED)
            ->where('end_time', '>=', now()->subDays($lookbackDays))
            ->whereNotNull('winning_bid_amount');

        if ($keywords->isNotEmpty()) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->orWhere('title', 'ilike', '%'.$keyword.'%');
                }
            });
        }

        $similar = $query->limit(200)->get(['id', 'winning_bid_amount', 'starting_price', 'created_at']);

        if ($similar->count() < $minSimilar) {
            $platform = Auction::query()
                ->where('status', Auction::STATUS_COMPLETED)
                ->where('end_time', '>=', now()->subDays($lookbackDays))
                ->whereNotNull('winning_bid_amount')
                ->limit(300)
                ->get(['id', 'winning_bid_amount', 'starting_price', 'created_at']);

            return $this->buildResponse($platform, $startingFactor, $reserveFactor, true);
        }

        return $this->buildResponse($similar, $startingFactor, $reserveFactor, false);
    }

    public function auctionInsights(Auction $auction): array
    {
        $snapshots = AuctionSnapshot::query()
            ->where('auction_id', $auction->id)
            ->orderBy('captured_at')
            ->get();

        $currentVelocity = $this->calculateVelocity($snapshots);

        $similar = Auction::query()
            ->where('status', Auction::STATUS_COMPLETED)
            ->where('id', '!=', $auction->id)
            ->where('title', 'ilike', '%'.str($auction->title)->explode(' ')->filter()->first().'%')
            ->whereNotNull('winning_bid_amount')
            ->limit(100)
            ->get();

        $avgFinal = (float) $similar->avg('winning_bid_amount');
        $predictedFinal = max((float) $auction->current_price, $avgFinal > 0 ? $avgFinal : (float) $auction->current_price * 1.1);

        $watchers = $auction->watchers()->count();
        $bidders = $auction->bids()->distinct('user_id')->count('user_id');
        $watcherToBidder = $watchers > 0 ? round(($bidders / $watchers) * 100, 2) : 0;

        $elapsedSeconds = max(1, now()->diffInSeconds($auction->start_time ?? now()));
        $durationSeconds = max(1, ($auction->end_time?->timestamp ?? now()->timestamp) - ($auction->start_time?->timestamp ?? now()->timestamp));
        $progress = min(1, $elapsedSeconds / $durationSeconds);

        $healthScore = (int) min(100, max(0, round(
            (($auction->bid_count * 2) + ($watchers * 1.2) + ($bidders * 2.5)) * (1 - ($progress * 0.2))
        )));

        return [
            'current_bid_velocity' => $currentVelocity,
            'predicted_final_price' => round($predictedFinal, 2),
            'watcher_to_bidder_conversion_rate' => $watcherToBidder,
            'health_score' => $healthScore,
            'similar_average_final_price' => round($avgFinal, 2),
        ];
    }

    private function buildResponse(Collection $auctions, float $startingFactor, float $reserveFactor, bool $fallback): array
    {
        $winningPrices = $auctions->pluck('winning_bid_amount')->filter()->map(fn ($v) => (float) $v)->sort()->values();

        $median = $this->median($winningPrices);
        $suggestedStart = round($median * $startingFactor, 2);
        $suggestedReserve = round($median * $reserveFactor, 2);

        $bestStartTime = $auctions->groupBy(fn ($a) => optional($a->created_at)->format('D H:00'))
            ->sortByDesc(fn ($group) => $group->avg('winning_bid_amount'))
            ->keys()
            ->first();

        return [
            'fallback' => $fallback,
            'sample_size' => $auctions->count(),
            'median_winning_price' => round($median, 2),
            'suggested_starting_price' => $suggestedStart,
            'suggested_reserve_price' => $suggestedReserve,
            'optimal_duration_days' => 7,
            'best_start_window' => $bestStartTime,
        ];
    }

    private function median(Collection $values): float
    {
        $count = $values->count();
        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 0) {
            return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
        }

        return (float) $values[$middle];
    }

    private function calculateVelocity(Collection $snapshots): float
    {
        if ($snapshots->count() < 2) {
            return 0;
        }

        $first = $snapshots->first();
        $last = $snapshots->last();

        $deltaPrice = (float) $last->price - (float) $first->price;
        $deltaHours = max(1, $last->captured_at->diffInHours($first->captured_at));

        return round($deltaPrice / $deltaHours, 2);
    }
}
