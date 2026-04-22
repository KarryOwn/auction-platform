<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsCategorySnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->input('days', 30)));
        $from = now()->subDays($days)->toDateString();

        $data = AnalyticsCategorySnapshot::query()
            ->where('report_date', '>=', $from)
            ->with('category:id,name,slug')
            ->selectRaw('
                category_id,
                SUM(total_auctions) as total_auctions,
                SUM(completed_auctions) as total_sales,
                SUM(cancelled_auctions) as total_cancelled,
                AVG(sell_through_rate) as sell_through_rate,
                AVG(avg_final_price) as avg_price,
                AVG(avg_starting_price) as avg_starting_price,
                AVG(price_appreciation_pct) as avg_appreciation,
                SUM(total_bids) as total_bids,
                SUM(unique_bidders) as total_unique_bidders
            ')
            ->groupBy('category_id')
            ->orderByDesc('total_sales')
            ->paginate(20);

        return response()->json($data);
    }
}
