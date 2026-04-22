<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->input('days', 30)));
        $from = now()->subDays($days);

        $buyers = User::query()
            ->select(['id', 'name', 'email', 'wallet_balance'])
            ->withCount([
                'bids as total_bids' => fn ($query) => $query->where('created_at', '>=', $from),
                'wonAuctions as auctions_won' => fn ($query) => $query->where('closed_at', '>=', $from),
            ])
            ->whereHas('bids', fn ($query) => $query->where('created_at', '>=', $from))
            ->orderByDesc('total_bids')
            ->paginate(25);

        return response()->json($buyers);
    }

    public function report(Request $request, User $user): JsonResponse
    {
        $days = max(1, min(365, (int) $request->input('days', 30)));
        $from = now()->subDays($days);

        $bids = $user->bids()->where('created_at', '>=', $from);
        $won = $user->wonAuctions()->where('closed_at', '>=', $from);

        $auctionsBidOn = (clone $bids)->distinct('auction_id')->count('auction_id');
        $auctionsWon = (clone $won)->count();

        return response()->json([
            'user_id' => $user->id,
            'name' => $user->name,
            'total_bids' => (clone $bids)->count(),
            'auctions_bid_on' => $auctionsBidOn,
            'auctions_won' => $auctionsWon,
            'total_spent' => (float) (clone $won)->sum('winning_bid_amount'),
            'avg_bid_amount' => (float) ((clone $bids)->avg('amount') ?? 0),
            'win_rate_pct' => $auctionsBidOn > 0
                ? round(($auctionsWon / $auctionsBidOn) * 100, 1)
                : 0,
            'wallet_balance' => (float) $user->wallet_balance,
        ]);
    }
}
