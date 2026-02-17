<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $seller = $request->user();
        $cacheKey = 'seller:dashboard:'.$seller->id;

        $stats = Cache::remember($cacheKey, 30, function () use ($seller) {
            $auctions = Auction::query()->where('user_id', $seller->id);
            $completed = (clone $auctions)->where('status', Auction::STATUS_COMPLETED);
            $completedCount = (clone $completed)->count();
            $completedWithWinner = (clone $completed)->whereNotNull('winner_id')->count();

            return [
                'total_auctions' => (clone $auctions)->count(),
                'active_auctions' => (clone $auctions)->where('status', Auction::STATUS_ACTIVE)->count(),
                'draft_auctions' => (clone $auctions)->where('status', Auction::STATUS_DRAFT)->count(),
                'completed_auctions' => $completedCount,
                'cancelled_auctions' => (clone $auctions)->where('status', Auction::STATUS_CANCELLED)->count(),
                'total_revenue' => (float) (clone $completed)->where('reserve_met', true)->sum('winning_bid_amount'),
                'total_bids_received' => Bid::query()->whereIn('auction_id', (clone $auctions)->select('id'))->count(),
                'active_watchers' => (clone $auctions)->where('status', Auction::STATUS_ACTIVE)->withCount('watchers')->get()->sum('watchers_count'),
                'conversion_rate' => $completedCount > 0 ? round(($completedWithWinner / $completedCount) * 100, 2) : 0,
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
            ->withCount('bids')
            ->orderBy('end_time')
            ->get();

        $recentActivity = Bid::query()
            ->with(['user:id,name', 'auction:id,title,user_id'])
            ->whereHas('auction', fn ($q) => $q->where('user_id', $seller->id))
            ->latest()
            ->limit(10)
            ->get();

        return view('seller.dashboard', compact('stats', 'activeListings', 'recentActivity'));
    }

    public function liveMetrics(Request $request): JsonResponse
    {
        $seller = $request->user();
        $auctionIds = Auction::query()->where('user_id', $seller->id)->pluck('id');

        return response()->json([
            'active_auction_count' => Auction::query()->where('user_id', $seller->id)->where('status', Auction::STATUS_ACTIVE)->count(),
            'bids_last_hour' => Bid::query()->whereIn('auction_id', $auctionIds)->where('created_at', '>=', now()->subHour())->count(),
            'revenue_today' => (float) Auction::query()->where('user_id', $seller->id)->where('status', Auction::STATUS_COMPLETED)->whereDate('closed_at', today())->sum('winning_bid_amount'),
            'unread_messages' => Conversation::query()->where('seller_id', $seller->id)->where(function ($q) { $q->whereNull('seller_read_at')->orWhereColumn('seller_read_at', '<', 'last_message_at'); })->count(),
        ]);
    }
}
