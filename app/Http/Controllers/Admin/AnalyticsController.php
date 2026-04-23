<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsCategorySnapshot;
use App\Models\AnalyticsHourlyBidVolume;
use App\Models\AnalyticsSellerSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        return view('admin.analytics.index');
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        abort_unless(in_array($report, ['categories', 'bid-timing', 'leaderboard', 'buyers'], true), 404);

        $days = max(1, min(365, (int) $request->input('days', $request->input('period', 30))));
        $fromDate = now()->subDays($days)->toDateString();
        $fromDateTime = now()->subDays($days);

        return response()->streamDownload(function () use ($report, $fromDate, $fromDateTime): void {
            $out = fopen('php://output', 'w');

            match ($report) {
                'categories' => $this->writeCategoryExport($out, $fromDate),
                'bid-timing' => $this->writeBidTimingExport($out, $fromDate),
                'leaderboard' => $this->writeLeaderboardExport($out, $fromDate),
                'buyers' => $this->writeBuyerExport($out, $fromDateTime),
            };

            fclose($out);
        }, "admin-analytics-{$report}-" . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function writeCategoryExport($out, string $fromDate): void
    {
        fputcsv($out, ['Category', 'Auctions', 'Sales', 'Cancelled', 'Sell Through', 'Average Price', 'Appreciation %', 'Bids', 'Unique Bidders']);

        AnalyticsCategorySnapshot::query()
            ->where('report_date', '>=', $fromDate)
            ->with('category:id,name')
            ->selectRaw('category_id, SUM(total_auctions) as total_auctions, SUM(completed_auctions) as total_sales, SUM(cancelled_auctions) as total_cancelled, AVG(sell_through_rate) as sell_through_rate, AVG(avg_final_price) as avg_price, AVG(price_appreciation_pct) as avg_appreciation, SUM(total_bids) as total_bids, SUM(unique_bidders) as total_unique_bidders')
            ->groupBy('category_id')
            ->orderByDesc('total_sales')
            ->get()
            ->each(fn ($row) => fputcsv($out, [
                $row->category?->name ?? 'Unknown',
                $row->total_auctions,
                $row->total_sales,
                $row->total_cancelled,
                round((float) $row->sell_through_rate, 4),
                round((float) $row->avg_price, 2),
                round((float) $row->avg_appreciation, 2),
                $row->total_bids,
                $row->total_unique_bidders,
            ]));
    }

    protected function writeBidTimingExport($out, string $fromDate): void
    {
        fputcsv($out, ['Day', 'Hour UTC', 'Total Bids', 'Average Bids', 'Average Unique Bidders', 'Average Unique Auctions']);

        AnalyticsHourlyBidVolume::query()
            ->where('report_date', '>=', $fromDate)
            ->selectRaw('hour_of_day, day_of_week, SUM(bid_count) as total_bids, AVG(bid_count) as avg_bids, AVG(unique_bidders) as avg_unique_bidders, AVG(unique_auctions) as avg_unique_auctions')
            ->groupBy('hour_of_day', 'day_of_week')
            ->orderBy('day_of_week')
            ->orderBy('hour_of_day')
            ->get()
            ->each(fn ($row) => fputcsv($out, [
                $row->day_of_week,
                $row->hour_of_day,
                $row->total_bids,
                round((float) $row->avg_bids, 2),
                round((float) $row->avg_unique_bidders, 2),
                round((float) $row->avg_unique_auctions, 2),
            ]));
    }

    protected function writeLeaderboardExport($out, string $fromDate): void
    {
        fputcsv($out, ['Seller', 'Slug', 'Revenue', 'Sales', 'Average Rating', 'Active Listings', 'Bids Received']);

        AnalyticsSellerSnapshot::query()
            ->where('report_date', '>=', $fromDate)
            ->with('user:id,name,seller_slug')
            ->selectRaw('user_id, SUM(gross_revenue) as total_revenue, SUM(completed_sales) as total_sales, AVG(avg_rating) as avg_rating, SUM(active_listings) as active_listings, SUM(total_bids_received) as total_bids_received')
            ->groupBy('user_id')
            ->orderByDesc('total_revenue')
            ->limit(500)
            ->get()
            ->each(fn ($row) => fputcsv($out, [
                $row->user?->name ?? 'Unknown',
                $row->user?->seller_slug ?? '',
                round((float) $row->total_revenue, 2),
                $row->total_sales,
                round((float) $row->avg_rating, 2),
                $row->active_listings,
                $row->total_bids_received,
            ]));
    }

    protected function writeBuyerExport($out, Carbon $fromDateTime): void
    {
        fputcsv($out, ['Buyer', 'Email', 'Bids', 'Wins', 'Wallet Balance']);

        User::query()
            ->select(['id', 'name', 'email', 'wallet_balance'])
            ->withCount([
                'bids as total_bids' => fn ($query) => $query->where('created_at', '>=', $fromDateTime),
                'wonAuctions as auctions_won' => fn ($query) => $query->where('closed_at', '>=', $fromDateTime),
            ])
            ->whereHas('bids', fn ($query) => $query->where('created_at', '>=', $fromDateTime))
            ->orderByDesc('total_bids')
            ->limit(1000)
            ->get()
            ->each(fn ($row) => fputcsv($out, [
                $row->name,
                $row->email,
                $row->total_bids,
                $row->auctions_won,
                round((float) $row->wallet_balance, 2),
            ]));
    }
}
