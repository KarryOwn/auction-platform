<?php

namespace App\Http\Controllers;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\AuctionWatcher;
use App\Models\AutoBid;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function __construct(
        protected BiddingStrategy $biddingStrategy,
    ) {}

    public function index()
    {
        $auctions = Auction::where('status', Auction::STATUS_ACTIVE)
            ->where('end_time', '>', now())
            ->withCount('bids')
            ->orderBy('end_time', 'asc')
            ->paginate(12);

        // Sync each auction's current_price from the bidding engine (Redis may be ahead of DB)
        foreach ($auctions as $auction) {
            $auction->current_price = $this->biddingStrategy->getCurrentPrice($auction);
        }

        return view('auctions.index', compact('auctions'));
    }

    public function show(Auction $auction)
    {
        $auction->loadCount('bids');
        $auction->load(['seller:id,name', 'highestBid.user:id,name']);

        // Get the real-time price from the bidding engine (Redis may be ahead of DB)
        $auction->current_price = $this->biddingStrategy->getCurrentPrice($auction);

        $user = auth()->user();

        $isWatching = $user
            ? AuctionWatcher::where('auction_id', $auction->id)->where('user_id', $user->id)->exists()
            : false;

        $autoBid = $user
            ? AutoBid::where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first()
            : null;

        $recentBids = $auction->bids()
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get();

        return view('auctions.show', compact(
            'auction',
            'isWatching',
            'autoBid',
            'recentBids',
        ));
    }

    /**
     * Toggle watch status for an auction.
     */
    public function toggleWatch(Request $request, Auction $auction)
    {
        $user = $request->user();

        $watcher = AuctionWatcher::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->first();

        if ($watcher) {
            $watcher->delete();

            return response()->json(['watching' => false, 'message' => 'Removed from watchlist.']);
        }

        AuctionWatcher::create([
            'auction_id'       => $auction->id,
            'user_id'          => $user->id,
            'notify_outbid'    => true,
            'notify_ending'    => true,
            'notify_cancelled' => true,
        ]);

        return response()->json(['watching' => true, 'message' => 'Added to watchlist!']);
    }

    /**
     * Set or update an auto-bid for the auction.
     */
    public function setAutoBid(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'max_amount' => 'required|numeric|min:' . $auction->minimumNextBid(),
        ]);

        $autoBid = AutoBid::updateOrCreate(
            [
                'auction_id' => $auction->id,
                'user_id'    => $request->user()->id,
            ],
            [
                'max_amount'     => $validated['max_amount'],
                'bid_increment'  => $auction->min_bid_increment,
                'is_active'      => true,
            ],
        );

        return response()->json([
            'success'    => true,
            'message'    => 'Auto-bid set up to $' . number_format($autoBid->max_amount, 2),
            'auto_bid'   => $autoBid,
        ]);
    }

    /**
     * Cancel an existing auto-bid.
     */
    public function cancelAutoBid(Request $request, Auction $auction)
    {
        AutoBid::where('auction_id', $auction->id)
            ->where('user_id', $request->user()->id)
            ->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Auto-bid cancelled.']);
    }
}
