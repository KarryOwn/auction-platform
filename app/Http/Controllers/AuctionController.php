<?php

namespace App\Http\Controllers;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\AuctionWatcher;
use App\Models\AutoBid;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function __construct(
        protected BiddingStrategy $biddingStrategy,
        protected CategoryService $categoryService,
    ) {}

    public function index(Request $request)
    {
        $query = Auction::active()
            ->with(['primaryCategory', 'media', 'brand', 'tags'])
            ->withCount('bids');

        // Keyword search
        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($searchQuery) use ($q) {
                $searchQuery->where('title', 'ilike', "%{$q}%")
                    ->orWhereHas('seller', function ($sellerQuery) use ($q) {
                        $sellerQuery->where('name', 'ilike', "%{$q}%");
                    });
            });
        }

        // Price range
        if ($minPrice = $request->input('min_price')) {
            $query->where('current_price', '>=', (float) $minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('current_price', '<=', (float) $maxPrice);
        }

        // Category filter
        if ($categorySlug = $request->input('category')) {
            $category = \App\Models\Category::where('slug', $categorySlug)->first();
            if ($category) {
                $categoryIds = array_merge([$category->id], $category->descendant_ids);
                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                });
            }
        }

        // Condition filter
        if ($condition = $request->input('condition')) {
            $query->where('condition', $condition);
        }

        // Brand filter
        if ($brandId = $request->input('brand_id')) {
            $query->where('brand_id', $brandId);
        }

        // Tag filter
        if ($tag = $request->input('tag')) {
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('slug', $tag);
            });
        }

        if ($request->boolean('authenticated_only')) {
            $query->where('has_authenticity_cert', true)
                ->where('authenticity_cert_status', 'verified');
        }

        // Sort
        $sort = $request->input('sort', 'ending_soon');
        $query->when($sort === 'ending_soon', fn ($q) => $q->orderBy('end_time', 'asc'))
              ->when($sort === 'newest', fn ($q) => $q->orderByDesc('created_at'))
              ->when($sort === 'price_asc', fn ($q) => $q->orderBy('current_price', 'asc'))
              ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('current_price'));

        $auctions = $query->paginate(12)->withQueryString();

        // Sync each auction's current_price from the bidding engine (Redis may be ahead of DB)
        foreach ($auctions as $auction) {
            $auction->current_price = $this->biddingStrategy->getCurrentPrice($auction);
        }

        $rootCategories = $this->categoryService->getRootWithAuctionCounts();
        $conditions = Auction::CONDITIONS;

        return view('auctions.index', compact('auctions', 'rootCategories', 'conditions'));
    }

    public function show(Auction $auction, \App\Services\AttributePricePredictionService $predictionService)
    {
        $auction->loadCount('bids');
        $auction->load([
            'seller',
            'categories',
            'media',
            'brand',
            'tags',
            'attributeValues.attribute',
            'highestBid.user',
            'winner',
        ]);

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

        $bidChartData = $auction->bids()
            ->orderBy('created_at')
            ->get(['amount', 'created_at'])
            ->map(fn ($b) => [
                'x' => $b->created_at->toIso8601String(),
                'y' => (float) $b->amount,
            ])
            ->values()
            ->toJson();

        $questions = $auction->questions()
            ->visible()
            ->with([
                'user:id,name',
                'answerer:id,name',
            ])
            ->oldest()
            ->get();

        $prediction = null;
        if ($auction->primaryCategory->isNotEmpty()) {
            $inputAttrs = $auction->attributeValues->mapWithKeys(function ($attrVal) {
                return [$attrVal->attribute->slug => $attrVal->value];
            })->toArray();
            
            $prediction = $predictionService->predict(
                categoryId: $auction->primaryCategory->first()->id,
                inputAttrs: $inputAttrs,
                brandId: $auction->brand_id,
                condition: $auction->condition
            );
        }

        $returnPolicy = $auction->effective_return_policy;

        return view('auctions.show', compact(
            'auction',
            'isWatching',
            'autoBid',
            'recentBids',
            'bidChartData',
            'questions',
            'prediction',
            'returnPolicy'
        ));
    }

    /**
     * Get a compact live snapshot for realtime UI fallback sync.
     */
    public function liveState(Auction $auction, Request $request)
    {
        $auction->loadCount('bids');
        $auction->load(['highestBid.user']);

        $auction->current_price = $this->biddingStrategy->getCurrentPrice($auction);

        $recentBids = $auction->bids()
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($bid) => [
                'id' => (int) $bid->id,
                'amount' => (float) $bid->amount,
                'bid_type' => $bid->bid_type ?? 'manual',
                'is_snipe_bid' => (bool) $bid->is_snipe_bid,
                'bidder_name' => $bid->user?->name ?? 'Unknown',
                'created_at_human' => $bid->created_at?->diffForHumans() ?? 'just now',
            ])
            ->values();

        $resource = new \App\Http\Resources\AuctionResource($auction);

        return response()->json(array_merge(
            $resource->toArray($request),
            [
                'auction_id' => (int) $auction->id,
                'new_price' => (float) $auction->current_price,
                'recent_bids' => $recentBids,
            ]
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

        // If the request specifically has thresholds and we are already watching, update it instead of toggling off
        if ($watcher && ($request->has('outbid_threshold') || $request->has('price_alert_at'))) {
             $watcher->update([
                 'outbid_threshold_amount' => $request->input('outbid_threshold'),
                 'price_alert_at'          => $request->input('price_alert_at'),
                 'price_alert_sent'        => false,
             ]);
             return response()->json(['watching' => true, 'message' => 'Watchlist preferences updated.']);
        }

        if ($watcher) {
            $watcher->delete();

            return response()->json(['watching' => false, 'message' => 'Removed from watchlist.']);
        }

        AuctionWatcher::create([
            'auction_id'             => $auction->id,
            'user_id'                => $user->id,
            'notify_outbid'          => true,
            'notify_ending'          => true,
            'notify_cancelled'       => true,
            'outbid_threshold_amount'=> $request->input('outbid_threshold'),
            'price_alert_at'         => $request->input('price_alert_at'),
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
                'max_auto_bids'  => AutoBid::DEFAULT_MAX_AUTO_BIDS,
                'auto_bids_used' => 0,
                'last_triggered_at' => null,
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
