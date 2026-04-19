<?php

namespace App\Http\Controllers\Admin;

use App\Events\AuctionCancelled;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Bid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class AuctionManagementController extends Controller
{
    /**
     * List auctions with optional filters.
     */
    public function index(Request $request)
    {
        $query = Auction::with('seller:id,name,email')->withCount('bids');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'ilike', "%{$search}%")
                    ->orWhereHas('seller', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        $auctions = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $statusCounts = [
            'all'       => Auction::count(),
            'active'    => Auction::where('status', 'active')->count(),
            'completed' => Auction::where('status', 'completed')->count(),
            'cancelled' => Auction::where('status', 'cancelled')->count(),
            'draft'     => Auction::where('status', 'draft')->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json($auctions);
        }

        return view('admin.auctions.index', compact('auctions', 'statusCounts'));
    }

    /**
     * Detailed view of a single auction for admin.
     */
    public function show(Request $request, Auction $auction)
    {
        $auction->load(['seller:id,name,email']);

        $bidStats = [
            'total_bids'      => $auction->bids()->count(),
            'unique_bidders'  => $auction->bids()->distinct('user_id')->count('user_id'),
            'highest_bid'     => $auction->bids()->max('amount'),
            'lowest_bid'      => $auction->bids()->min('amount'),
            'avg_bid'         => round((float) $auction->bids()->avg('amount'), 2),
            'last_bid_at'     => $auction->bids()->max('created_at'),
            'redis_price'     => $this->getRedisPrice($auction),
        ];

        $recentBids = $auction->bids()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'user_id', 'amount', 'ip_address', 'user_agent', 'created_at']);

        $suspiciousActivity = $this->detectSuspiciousActivity($auction);

        if ($request->wantsJson()) {
            return response()->json([
                'auction'             => $auction,
                'bid_stats'           => $bidStats,
                'recent_bids'         => $recentBids,
                'suspicious_activity' => $suspiciousActivity,
            ]);
        }

        return view('admin.auctions.show', compact('auction', 'bidStats', 'recentBids', 'suspiciousActivity'));
    }

    // force cancel auction
    public function forceCancel(Request $request, Auction $auction): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($auction->status === 'cancelled') {
            return response()->json(['message' => 'Auction is already cancelled.'], 422);
        }

        $oldStatus = $auction->status;
        $auction->update(['status' => 'cancelled']);

        // Clean up Redis
        Redis::del([
            "auction:{$auction->id}:price",
            "auction:{$auction->id}:meta",
            "auction:{$auction->id}:leaderboard",
        ]);

        AuditLog::record(
            action: 'auction.force_cancelled',
            targetType: 'auction',
            targetId: $auction->id,
            metadata: [
                'previous_status' => $oldStatus,
                'reason'          => $request->input('reason'),
                'current_price'   => $auction->current_price,
                'bid_count'       => $auction->bids()->count(),
            ],
        );

        AuctionCancelled::dispatch($auction->fresh(), $request->input('reason'));

        return response()->json([
            'message' => "Auction #{$auction->id} has been force-cancelled.",
            'auction' => $auction->fresh(),
        ]);
    }

    // extend auction time
    public function extend(Request $request, Auction $auction): JsonResponse
    {
        $request->validate([
            'minutes' => 'required|integer|min:1|max:1440',
            'reason'  => 'required|string|max:500',
        ]);

        if ($auction->status !== 'active') {
            return response()->json(['message' => 'Can only extend active auctions.'], 422);
        }

        $oldEndTime = $auction->end_time->toIso8601String();
        $auction->end_time = $auction->end_time->addMinutes($request->input('minutes'));
        $auction->save();

        AuditLog::record(
            action: 'auction.extended',
            targetType: 'auction',
            targetId: $auction->id,
            metadata: [
                'old_end_time' => $oldEndTime,
                'new_end_time' => $auction->end_time->toIso8601String(),
                'minutes'      => $request->input('minutes'),
                'reason'       => $request->input('reason'),
            ],
        );

        return response()->json([
            'message' => "Auction #{$auction->id} extended by {$request->input('minutes')} minutes.",
            'auction' => $auction,
        ]);
    }

    /**
     * Mark an auction as featured for a given duration.
     */
    public function feature(Request $request, Auction $auction): JsonResponse
    {
        $validated = $request->validate([
            'duration_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'position' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $featuredUntil = now()->addHours((int) $validated['duration_hours']);

        $auction->update([
            'is_featured' => true,
            'featured_until' => $featuredUntil,
            'featured_position' => $validated['position'] ?? null,
        ]);

        AuditLog::record(
            action: 'auction.featured',
            targetType: 'auction',
            targetId: $auction->id,
            metadata: [
                'duration_hours' => (int) $validated['duration_hours'],
                'featured_until' => $featuredUntil->toIso8601String(),
                'featured_position' => $validated['position'] ?? null,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Auction featured until '.$featuredUntil->format('M d, Y H:i'),
            'data' => [
                'is_featured' => true,
                'featured_until_iso' => $featuredUntil->toIso8601String(),
                'featured_until_human' => $featuredUntil->format('M d, Y H:i'),
                'featured_position' => $auction->featured_position,
            ],
        ]);
    }

    /**
     * Remove featured state from an auction.
     */
    public function unfeature(Auction $auction): JsonResponse
    {
        $auction->update([
            'is_featured' => false,
            'featured_until' => null,
            'featured_position' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feature removed from auction.',
            'data' => [
                'is_featured' => false,
                'featured_until_iso' => null,
                'featured_until_human' => null,
                'featured_position' => null,
            ],
        ]);
    }

    // Detect supicious activity 
    private function detectSuspiciousActivity(Auction $auction): array
    {
        $flags = [];

        // 1. Same IP placing many bids (possible shill bidding)
        $ipCounts = Bid::where('auction_id', $auction->id)
            ->select('ip_address', \DB::raw('count(*) as cnt'), \DB::raw('count(distinct user_id) as users'))
            ->groupBy('ip_address')
            ->having(\DB::raw('count(distinct user_id)'), '>', 1)
            ->get();

        foreach ($ipCounts as $ip) {
            if ($ip->users > 1) {
                $flags[] = [
                    'type'    => 'shared_ip',
                    'detail'  => "IP {$ip->ip_address} used by {$ip->users} different users ({$ip->cnt} total bids)",
                    'severity'=> 'warning',
                ];
            }
        }

        // 2. Rapid-fire bidding from single user
        $rapidBidders = Bid::where('auction_id', $auction->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->select('user_id', \DB::raw('count(*) as cnt'))
            ->groupBy('user_id')
            ->having(\DB::raw('count(*)'), '>', 10)
            ->get();

        foreach ($rapidBidders as $bidder) {
            $flags[] = [
                'type'     => 'rapid_bidding',
                'detail'   => "User #{$bidder->user_id} placed {$bidder->cnt} bids in last 5 minutes",
                'severity' => 'high',
            ];
        }

        // 3. Seller's bids on their own auction (should never happen with policy, but check DB)
        $sellerBids = Bid::where('auction_id', $auction->id)
            ->where('user_id', $auction->user_id)
            ->count();

        if ($sellerBids > 0) {
            $flags[] = [
                'type'     => 'seller_bidding',
                'detail'   => "Seller (User #{$auction->user_id}) has {$sellerBids} bids on own auction",
                'severity' => 'critical',
            ];
        }

        return $flags;
    }

    private function getRedisPrice(Auction $auction): ?string
    {
        try {
            return Redis::get("auction:{$auction->id}:price");
        } catch (\Exception $e) {
            return null;
        }
    }
}