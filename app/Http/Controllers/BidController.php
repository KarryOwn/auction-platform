<?php

namespace App\Http\Controllers;

use App\Contracts\BiddingStrategy;
use App\Models\Bid;
use App\Models\Auction;
use Illuminate\Http\Request;

class BidController extends Controller
{
    protected $biddingStrategy;

    // Inject the Interface. 
    // Laravel automatically gives us 'PessimisticSqlEngine' because of AppServiceProvider.
    public function __construct(BiddingStrategy $biddingStrategy)
    {
        $this->biddingStrategy = $biddingStrategy;
    }

    public function store(Request $request, Auction $auction)
    {
        // Validate the Request
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        try {
            // Call the Engine
            $bid = $this->biddingStrategy->placeBid(
                $auction,
                $request->user(), // The logged-in user
                $validated['amount']
            );

            // Return Success Response
            return response()->json([
                'success' => true,
                'message' => 'Bid accepted!',
                'new_price' => $bid->amount,
            ]);

        } catch (Exception $e) {
            // Handle "Race Condition" Failures or Logic Errors
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422); // 422 Unprocessable Entity
        }
    }
}
