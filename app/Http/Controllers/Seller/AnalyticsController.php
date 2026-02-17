<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionView;
use App\Models\Bid;
use App\Models\AuctionWatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $sellerId = $request->user()->id;

        $auctionIds = Auction::query()->where('user_id', $sellerId)->pluck('id');

        $metrics = [
            'total_views' => AuctionView::query()->whereIn('auction_id', $auctionIds)->whereBetween('viewed_at', [$from, $to])->count(),
            'unique_viewers' => AuctionView::query()->whereIn('auction_id', $auctionIds)->whereBetween('viewed_at', [$from, $to])->distinct('session_id')->count('session_id'),
            'total_bids' => Bid::query()->whereIn('auction_id', $auctionIds)->whereBetween('created_at', [$from, $to])->count(),
            'unique_bidders' => Bid::query()->whereIn('auction_id', $auctionIds)->whereBetween('created_at', [$from, $to])->distinct('user_id')->count('user_id'),
            'watchers' => AuctionWatcher::query()->whereIn('auction_id', $auctionIds)->count(),
            'winners' => Auction::query()->whereIn('id', $auctionIds)->where('status', Auction::STATUS_COMPLETED)->whereNotNull('winner_id')->count(),
        ];

        $topAuctions = Auction::query()
            ->where('user_id', $sellerId)
            ->withCount(['views', 'bids'])
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        return view('seller.analytics.index', compact('metrics', 'topAuctions', 'from', 'to'));
    }

    public function viewData(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $sellerId = $request->user()->id;

        $data = AuctionView::query()
            ->join('auctions', 'auctions.id', '=', 'auction_views.auction_id')
            ->where('auctions.user_id', $sellerId)
            ->whereBetween('auction_views.viewed_at', [$from, $to])
            ->select(DB::raw("DATE(auction_views.viewed_at) as day"), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function bidData(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $sellerId = $request->user()->id;

        $data = Bid::query()
            ->join('auctions', 'auctions.id', '=', 'bids.auction_id')
            ->where('auctions.user_id', $sellerId)
            ->whereBetween('bids.created_at', [$from, $to])
            ->select(DB::raw("DATE(bids.created_at) as day"), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function conversionData(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $sellerId = $request->user()->id;

        $auctionIds = Auction::query()
            ->where('user_id', $sellerId)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id');

        $data = [
            'views' => AuctionView::query()->whereIn('auction_id', $auctionIds)->count(),
            'watchers' => AuctionWatcher::query()->whereIn('auction_id', $auctionIds)->count(),
            'bidders' => Bid::query()->whereIn('auction_id', $auctionIds)->distinct('user_id')->count('user_id'),
            'winners' => Auction::query()->whereIn('id', $auctionIds)->whereNotNull('winner_id')->count(),
        ];

        return response()->json(['data' => $data]);
    }

    private function range(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        return [$from, $to];
    }
}
