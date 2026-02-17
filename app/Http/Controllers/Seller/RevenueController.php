<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $sellerId = $request->user()->id;

        $query = Auction::query()
            ->where('user_id', $sellerId)
            ->where('status', Auction::STATUS_COMPLETED)
            ->whereBetween('closed_at', [$from, $to])
            ->with('winner:id,name')
            ->orderByDesc('closed_at');

        $rows = (clone $query)->paginate(20)->withQueryString();

        $summary = [
            'total_revenue' => (float) (clone $query)->sum('winning_bid_amount'),
            'month_revenue' => (float) Auction::query()->where('user_id', $sellerId)->where('status', Auction::STATUS_COMPLETED)->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('winning_bid_amount'),
            'average_sale_price' => round((float) (clone $query)->avg('winning_bid_amount'), 2),
            'highest_sale' => (float) (clone $query)->max('winning_bid_amount'),
        ];

        $chartMonthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-01', closed_at)",
            'mysql' => "DATE_FORMAT(closed_at, '%Y-%m-01')",
            default => "DATE_TRUNC('month', closed_at)",
        };

        $chart = Auction::query()
            ->where('user_id', $sellerId)
            ->where('status', Auction::STATUS_COMPLETED)
            ->whereBetween('closed_at', [$from, $to])
            ->select(DB::raw($chartMonthExpression.' as month'), DB::raw('SUM(winning_bid_amount) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('seller.revenue.index', compact('rows', 'summary', 'chart', 'from', 'to'));
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $rows = Auction::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Auction::STATUS_COMPLETED)
            ->whereBetween('closed_at', [$from, $to])
            ->with('winner:id,name')
            ->orderByDesc('closed_at')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Auction', 'Winning Bid', 'Sale Date', 'Buyer', 'Reserve Met']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->title,
                    (float) $row->winning_bid_amount,
                    optional($row->closed_at)->toDateString(),
                    $row->winner?->name,
                    $row->reserve_met ? 'yes' : 'no',
                ]);
            }

            fclose($handle);
        }, 'seller-revenue.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function range(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        return [$from, $to];
    }
}
