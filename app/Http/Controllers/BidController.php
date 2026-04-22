<?php

namespace App\Http\Controllers;

use App\Contracts\BiddingStrategy;
use App\Exceptions\BidValidationException;
use App\Http\Requests\PlaceBidRequest;
use App\Models\Auction;

class BidController extends Controller
{
    public function __construct(
        protected BiddingStrategy $biddingStrategy,
    ) {}

    public function store(PlaceBidRequest $request, Auction $auction)
    {
        $validated = $request->validated();

        try {
            $displayCurrency = display_currency();
            $bid = $this->biddingStrategy->placeBid(
                auction: $auction,
                user: $request->user(),
                amount: (float) $validated['amount'],
                meta: [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            );

            $freshAuction = $auction->fresh();
            $minimumNextBid = (float) $freshAuction->minimumNextBid();

            return response()->json([
                'success'   => true,
                'message'   => 'Bid accepted!',
                'new_price' => (float) $bid->amount,
                'display_price' => format_price((float) $bid->amount, $displayCurrency),
                'display_currency' => $displayCurrency,
                'minimum_next_bid' => $minimumNextBid,
                'formatted_minimum_next_bid' => format_price($minimumNextBid, $displayCurrency),
                'bid_type'  => $bid->bid_type ?? 'manual',
            ]);
        } catch (BidValidationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
