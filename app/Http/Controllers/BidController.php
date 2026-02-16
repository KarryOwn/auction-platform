<?php

namespace App\Http\Controllers;

use App\Contracts\BiddingStrategy;
use App\Exceptions\BidValidationException;
use App\Models\Auction;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function __construct(
        protected BiddingStrategy $biddingStrategy,
    ) {}

    public function store(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $bid = $this->biddingStrategy->placeBid(
                auction: $auction,
                user: $request->user(),
                amount: (float) $validated['amount'],
                meta: [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            );

            return response()->json([
                'success'   => true,
                'message'   => 'Bid accepted!',
                'new_price' => (float) $bid->amount,
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
