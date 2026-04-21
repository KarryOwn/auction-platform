<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\BidRetractionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidRetractionController extends Controller
{
    public function store(Request $request, Bid $bid): JsonResponse
    {
        $user = $request->user();

        if ($bid->user_id !== $user->id) {
            return response()->json(['error' => 'You do not own this bid.'], 403);
        }

        $auction = $bid->auction;

        if (!$auction || !$auction->isActive()) {
            return response()->json(['error' => 'Retractions are only allowed on active auctions.'], 422);
        }

        $highestBid = $auction->bids()->where('is_retracted', false)->orderByDesc('amount')->first();

        if (!$highestBid || $highestBid->id !== $bid->id) {
            return response()->json(['error' => 'You can only request retraction for your current highest bid.'], 422);
        }

        if (BidRetractionRequest::where('bid_id', $bid->id)->exists()) {
            return response()->json(['error' => 'A retraction request has already been filed for this bid.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        BidRetractionRequest::create([
            'bid_id'     => $bid->id,
            'user_id'    => $user->id,
            'auction_id' => $auction->id,
            'reason'     => $validated['reason'],
        ]);

        return response()->json(['message' => 'Bid retraction request submitted.']);
    }
}
