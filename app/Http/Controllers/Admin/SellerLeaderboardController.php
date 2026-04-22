<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsSellerSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerLeaderboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = max(1, min(365, (int) $request->input('period', 30)));
        $from = now()->subDays($period)->toDateString();

        $leaderboard = AnalyticsSellerSnapshot::query()
            ->where('report_date', '>=', $from)
            ->with('user:id,name,seller_slug,seller_verified_at')
            ->selectRaw('
                user_id,
                SUM(gross_revenue) as total_revenue,
                SUM(completed_sales) as total_sales,
                AVG(avg_rating) as avg_rating,
                SUM(active_listings) as active_listings,
                SUM(total_bids_received) as total_bids_received
            ')
            ->groupBy('user_id')
            ->orderByDesc('total_revenue')
            ->limit(50)
            ->get();

        return response()->json(['data' => $leaderboard]);
    }
}
