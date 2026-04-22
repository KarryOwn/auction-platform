<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsHourlyBidVolume;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidTimingController extends Controller
{
    public function heatmap(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->input('days', 30)));
        $from = now()->subDays($days)->toDateString();

        $data = AnalyticsHourlyBidVolume::query()
            ->where('report_date', '>=', $from)
            ->selectRaw('
                hour_of_day,
                day_of_week,
                SUM(bid_count) as total_bids,
                AVG(bid_count) as avg_bids,
                AVG(unique_bidders) as avg_unique_bidders,
                AVG(unique_auctions) as avg_unique_auctions
            ')
            ->groupBy('hour_of_day', 'day_of_week')
            ->orderBy('hour_of_day')
            ->get();

        $peak = $data->sortByDesc('avg_bids')->first();

        return response()->json([
            'heatmap' => $data->values(),
            'peak_hour' => $peak?->hour_of_day,
            'peak_day' => $peak?->day_of_week,
            'recommendation' => $peak
                ? "Best time to list: {$peak->day_of_week} at {$peak->hour_of_day}:00 UTC"
                : 'Insufficient data',
        ]);
    }
}
