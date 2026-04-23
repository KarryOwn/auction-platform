<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsSellerSnapshot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerLeaderboardPublicController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = max(7, min(365, (int) $request->input('period', 30)));
        $from = now()->subDays($period)->toDateString();

        $leaders = AnalyticsSellerSnapshot::query()
            ->where('report_date', '>=', $from)
            ->whereHas('user', fn ($query) => $query->verifiedSellers())
            ->with('user:id,name,seller_slug,seller_bio,seller_avatar_path,seller_verified_at')
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

        return view('seller.leaderboard', [
            'leaders' => $leaders,
            'period' => $period,
            'periodOptions' => [30 => '30 days', 90 => '90 days', 365 => 'Year'],
        ]);
    }
}
