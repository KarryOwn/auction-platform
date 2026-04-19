<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Conversation;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $seller = $request->user();
        $cacheKey = 'seller:dashboard:'.$seller->id;

        $stats = Cache::remember($cacheKey, 30, function () use ($seller) {
            $auctions = Auction::query()->where('user_id', $seller->id);
            $completed = (clone $auctions)->where('status', Auction::STATUS_COMPLETED);
            $totalCount = (clone $auctions)->count();
            $completedCount = (clone $completed)->count();
            $completedWithWinner = (clone $completed)->whereNotNull('winner_id')->count();
            $bidsToday = Bid::query()
                ->whereIn('auction_id', $seller->auctions()->select('id'))
                ->whereDate('created_at', today())
                ->count();

            return [
                'total_auctions' => $totalCount,
                'active_auctions' => (clone $auctions)->where('status', Auction::STATUS_ACTIVE)->count(),
                'draft_auctions' => (clone $auctions)->where('status', Auction::STATUS_DRAFT)->count(),
                'completed_auctions' => $completedCount,
                'cancelled_auctions' => (clone $auctions)->where('status', Auction::STATUS_CANCELLED)->count(),
                'total_revenue' => (float) (clone $completed)
                    ->where('reserve_met', true)
                    ->whereHas('bids')
                    ->sum('winning_bid_amount'),
                'total_bids_received' => Bid::query()->whereIn('auction_id', (clone $auctions)->select('id'))->count(),
                'bids_today' => (int) $bidsToday,
                'active_watchers' => (clone $auctions)->where('status', Auction::STATUS_ACTIVE)->withCount('watchers')->get()->sum('watchers_count'),
                'conversion_rate' => $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 2) : 0,
                'completed_with_winner' => $completedWithWinner,
                'unread_messages' => Conversation::query()
                    ->where('seller_id', $seller->id)
                    ->where(function ($q) {
                        $q->whereNull('seller_read_at')->orWhereColumn('seller_read_at', '<', 'last_message_at');
                    })->count(),
            ];
        });

        $activeListings = Auction::query()
            ->where('user_id', $seller->id)
            ->whereIn('status', [Auction::STATUS_ACTIVE, Auction::STATUS_DRAFT])
            ->with(['media'])
            ->withCount('bids')
            ->orderBy('end_time')
            ->get();

        $recentMessages = Conversation::query()
            ->where('seller_id', $seller->id)
            ->where(function ($q) {
                $q->whereNull('seller_read_at')->orWhereColumn('seller_read_at', '<', 'last_message_at');
            })
            ->with([
                'buyer:id,name',
                'auction:id,title',
                'messages' => fn ($q) => $q->latest()->limit(1),
            ])
            ->latest('last_message_at')
            ->take(3)
            ->get();

        $revenueChartData = json_encode($this->buildRevenueChartData($seller->id));

        $recentActivity = Bid::query()
            ->with(['user:id,name', 'auction:id,title,user_id'])
            ->whereHas('auction', fn ($q) => $q->where('user_id', $seller->id))
            ->latest()
            ->limit(10)
            ->get();

        return view('seller.dashboard', compact('stats', 'activeListings', 'recentActivity', 'recentMessages', 'revenueChartData'));
    }

    public function liveMetrics(Request $request): JsonResponse
    {
        $seller = $request->user();
        $auctionIds = Auction::query()->where('user_id', $seller->id)->pluck('id');

        return response()->json([
            'active_auction_count' => Auction::query()->where('user_id', $seller->id)->where('status', Auction::STATUS_ACTIVE)->count(),
            'bids_last_hour' => Bid::query()->whereIn('auction_id', $auctionIds)->where('created_at', '>=', now()->subHour())->count(),
            'bids_today' => Bid::query()->whereIn('auction_id', $auctionIds)->whereDate('created_at', today())->count(),
            'revenue_today' => (float) Auction::query()->where('user_id', $seller->id)->where('status', Auction::STATUS_COMPLETED)->whereDate('closed_at', today())->sum('winning_bid_amount'),
            'unread_messages' => Conversation::query()->where('seller_id', $seller->id)->where(function ($q) { $q->whereNull('seller_read_at')->orWhereColumn('seller_read_at', '<', 'last_message_at'); })->count(),
        ]);
    }

    public function revenueChartData(Request $request): JsonResponse
    {
        return response()->json($this->buildRevenueChartData($request->user()->id));
    }

    private function buildRevenueChartData(int $sellerId): array
    {
        $from = now()->subDays(29)->startOfDay();
        $to = now()->endOfDay();

        $rows = Auction::query()
            ->where('user_id', $sellerId)
            ->where('status', Auction::STATUS_COMPLETED)
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw('DATE(closed_at) as date, COALESCE(SUM(winning_bid_amount), 0) as revenue')
            ->groupBy(DB::raw('DATE(closed_at)'))
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->date => (float) $row->revenue]);

        return collect(CarbonPeriod::create($from, $to))
            ->map(fn ($day) => [
                'date' => $day->format('Y-m-d'),
                'revenue' => (float) ($rows[$day->format('Y-m-d')] ?? 0),
            ])
            ->values()
            ->all();
    }
}
