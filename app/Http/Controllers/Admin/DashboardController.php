<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\BiddingStrategy;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\ReportedAuction;
use App\Models\User;
use App\Services\Bidding\PessimisticSqlEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class DashboardController extends Controller
{
    /**
     * Main admin dashboard — platform overview.
     */
    public function index(Request $request)
    {
        $stats = Cache::remember('admin:dashboard:stats', 30, function () {
            return [
                'total_users'       => User::count(),
                'banned_users'      => User::where('is_banned', true)->count(),
                'active_auctions'   => Auction::where('status', 'active')->where('end_time', '>', now())->count(),
                'completed_auctions'=> Auction::where('status', 'completed')->count(),
                'total_bids_today'  => Bid::whereDate('created_at', today())->count(),
                'total_bids_hour'   => Bid::where('created_at', '>=', now()->subHour())->count(),
                'revenue_today'     => Auction::where('status', 'completed')->whereDate('updated_at', today())->sum('current_price'),
                'pending_reports'   => ReportedAuction::where('status', 'pending')->count(),
            ];
        });

        if ($request->wantsJson()) {
            return response()->json(['data' => $stats]);
        }

        return view('admin.dashboard', compact('stats'));
    }

    /**
     * Real-time metrics — designed for polling / live dashboard.
     */
    public function liveMetrics(): JsonResponse
    {
        $metrics = [
            'bids_last_minute'     => Bid::where('created_at', '>=', now()->subMinute())->count(),
            'bids_last_5_minutes'  => Bid::where('created_at', '>=', now()->subMinutes(5))->count(),
            'active_auctions'      => Auction::where('status', 'active')->where('end_time', '>', now())->count(),
            'ending_in_5_minutes'  => Auction::where('status', 'active')
                                        ->whereBetween('end_time', [now(), now()->addMinutes(5)])
                                        ->count(),
            'unique_bidders_hour'  => Bid::where('created_at', '>=', now()->subHour())
                                        ->distinct('user_id')
                                        ->count('user_id'),
            'top_auction'          => Auction::where('status', 'active')
                                        ->orderByDesc('current_price')
                                        ->select('id', 'title', 'current_price')
                                        ->first(),
            'redis_keys'           => $this->getRedisAuctionCount(),
            'engine_degraded'      => app(BiddingStrategy::class) instanceof PessimisticSqlEngine,
            'timestamp'            => now()->toIso8601String(),
        ];

        return response()->json(['data' => $metrics]);
    }

    /**
     * Bid throughput over time (for charts).
     */
    public function bidThroughput(Request $request): JsonResponse
    {
        $hours = $request->input('hours', 24);

        $throughput = Bid::where('created_at', '>=', now()->subHours($hours))
            ->select(
                DB::raw("date_trunc('hour', created_at) as hour"),
                DB::raw('count(*) as bid_count'),
                DB::raw('count(distinct user_id) as unique_bidders'),
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json(['data' => $throughput]);
    }

    /**
     * Recent fraud/suspicious activity alerts for admin dashboard.
     */
    public function fraudAlerts(): JsonResponse
    {
        $alerts = ReportedAuction::query()
            ->with(['auction:id,title'])
            ->where('created_at', '>=', now()->subHours(2))
            ->whereIn('reason', [
                'Seller fraud',
                'Counterfeit or fake item',
                'Prohibited item',
                'Item description inaccurate',
                'Other',
            ])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function (ReportedAuction $report) {
                $severity = match ($report->reason) {
                    'Seller fraud' => 'critical',
                    'Counterfeit or fake item', 'Prohibited item' => 'high',
                    default => 'warning',
                };

                return [
                    'auction_id' => $report->auction_id,
                    'auction_title' => $report->auction?->title,
                    'severity' => $severity,
                    'detail' => $report->description ?: $report->reason,
                    'detected_at' => optional($report->created_at)->toIso8601String(),
                ];
            })
            ->values();

        return response()->json(['data' => $alerts]);
    }

    /**
     * Count Redis auction price keys.
     */
    private function getRedisAuctionCount(): int
    {
        try {
            $keys = Redis::keys('auction:*:price');
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
