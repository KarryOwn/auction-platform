<?php

namespace App\Jobs;

use App\Models\AnalyticsCategorySnapshot;
use App\Models\AnalyticsHourlyBidVolume;
use App\Models\AnalyticsSellerSnapshot;
use App\Models\Auction;
use App\Models\AuctionRating;
use App\Models\Bid;
use App\Models\Category;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAnalyticsSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        $reportDate = now()->subDay()->toDateString();

        $this->generateCategorySnapshots($reportDate);
        $this->generateHourlyBidVolume($reportDate);
        $this->generateSellerSnapshots($reportDate);

        Log::info("GenerateAnalyticsSnapshot: completed for {$reportDate}");
    }

    private function generateCategorySnapshots(string $date): void
    {
        Category::query()->select(['id'])->chunkById(100, function ($categories) use ($date): void {
            foreach ($categories as $category) {
                $auctionIds = DB::table('auction_category')
                    ->where('category_id', $category->id)
                    ->pluck('auction_id');

                if ($auctionIds->isEmpty()) {
                    continue;
                }

                $dayAuctions = Auction::query()
                    ->whereIn('id', $auctionIds)
                    ->where(function ($query) use ($date) {
                        $query->whereDate('created_at', $date)
                            ->orWhereDate('closed_at', $date)
                            ->orWhereDate('updated_at', $date);
                    });

                $totalAuctions = (clone $dayAuctions)->count();

                $completedAuctionsQuery = (clone $dayAuctions)
                    ->where('status', Auction::STATUS_COMPLETED)
                    ->whereDate('closed_at', $date);

                $completedAuctions = (clone $completedAuctionsQuery)->count();
                $cancelledAuctions = (clone $dayAuctions)
                    ->where('status', Auction::STATUS_CANCELLED)
                    ->whereDate('updated_at', $date)
                    ->count();
                $endedWithoutWinner = (clone $completedAuctionsQuery)
                    ->whereNull('winner_id')
                    ->count();

                $avgFinal = (float) ((clone $completedAuctionsQuery)->avg('winning_bid_amount') ?? 0);
                $avgStart = (float) ((clone $completedAuctionsQuery)->avg('starting_price') ?? 0);
                $appreciation = (float) ((clone $completedAuctionsQuery)
                    ->selectRaw('AVG(CASE WHEN starting_price > 0 AND winning_bid_amount IS NOT NULL THEN ((winning_bid_amount - starting_price) / starting_price) * 100 ELSE 0 END) as appreciation')
                    ->value('appreciation') ?? 0);

                $dailyBids = Bid::query()
                    ->whereIn('auction_id', $auctionIds)
                    ->whereDate('created_at', $date);

                $sellThroughDenominator = $completedAuctions + $endedWithoutWinner;

                AnalyticsCategorySnapshot::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'report_date' => $date,
                    ],
                    [
                        'total_auctions' => $totalAuctions,
                        'completed_auctions' => $completedAuctions,
                        'cancelled_auctions' => $cancelledAuctions,
                        'sell_through_rate' => $sellThroughDenominator > 0
                            ? round($completedAuctions / $sellThroughDenominator, 4)
                            : 0,
                        'avg_final_price' => round($avgFinal, 2),
                        'avg_starting_price' => round($avgStart, 2),
                        'price_appreciation_pct' => round($appreciation, 4),
                        'total_bids' => (clone $dailyBids)->count(),
                        'unique_bidders' => (clone $dailyBids)->distinct('user_id')->count('user_id'),
                    ]
                );
            }
        });
    }

    private function generateHourlyBidVolume(string $date): void
    {
        $hourlyData = Bid::query()
            ->whereDate('created_at', $date)
            ->selectRaw("
                EXTRACT(HOUR FROM created_at)::int AS hour,
                TO_CHAR(created_at, 'Day') AS day_name,
                COUNT(*) AS bid_count,
                COUNT(DISTINCT user_id) AS unique_bidders,
                COUNT(DISTINCT auction_id) AS unique_auctions
            ")
            ->groupBy(DB::raw("EXTRACT(HOUR FROM created_at), TO_CHAR(created_at, 'Day')"))
            ->get();

        foreach ($hourlyData as $row) {
            AnalyticsHourlyBidVolume::updateOrCreate(
                [
                    'report_date' => $date,
                    'hour_of_day' => (int) $row->hour,
                ],
                [
                    'day_of_week' => strtolower(trim((string) $row->day_name)),
                    'bid_count' => (int) $row->bid_count,
                    'unique_bidders' => (int) $row->unique_bidders,
                    'unique_auctions' => (int) $row->unique_auctions,
                ]
            );
        }
    }

    private function generateSellerSnapshots(string $date): void
    {
        User::query()
            ->where('role', User::ROLE_SELLER)
            ->select(['id'])
            ->chunkById(100, function ($sellers) use ($date): void {
                foreach ($sellers as $seller) {
                    $completed = Auction::query()
                        ->where('user_id', $seller->id)
                        ->where('status', Auction::STATUS_COMPLETED)
                        ->whereDate('closed_at', $date);

                    $activeListings = Auction::query()
                        ->where('user_id', $seller->id)
                        ->where('status', Auction::STATUS_ACTIVE)
                        ->whereDate('created_at', '<=', $date)
                        ->count();

                    $totalBidsReceived = Bid::query()
                        ->whereHas('auction', fn ($query) => $query->where('user_id', $seller->id))
                        ->whereDate('created_at', $date)
                        ->count();

                    $avgRating = (float) (AuctionRating::query()
                        ->forSeller($seller->id)
                        ->avg('score') ?? 0);

                    AnalyticsSellerSnapshot::updateOrCreate(
                        [
                            'user_id' => $seller->id,
                            'report_date' => $date,
                        ],
                        [
                            'active_listings' => $activeListings,
                            'completed_sales' => (clone $completed)->count(),
                            'gross_revenue' => (float) (clone $completed)->sum('winning_bid_amount'),
                            'avg_sale_price' => round((float) ((clone $completed)->avg('winning_bid_amount') ?? 0), 2),
                            'avg_rating' => round($avgRating, 2),
                            'total_bids_received' => $totalBidsReceived,
                        ]
                    );
                }
            });
    }
}
