<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\ReportedAuction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuctionReportController extends Controller
{
    public function store(Request $request, Auction $auction): JsonResponse
    {
        if ((int) $request->user()->id === (int) $auction->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot report your own auction.',
            ], 403);
        }

        $reasons = [
            'Item description inaccurate',
            'Counterfeit or fake item',
            'Prohibited item',
            'Seller fraud',
            'Other',
        ];

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in($reasons)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $alreadyReported = ReportedAuction::query()
            ->where('auction_id', $auction->id)
            ->where('reporter_id', $request->user()->id)
            ->exists();

        if ($alreadyReported) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reported this auction.',
            ], 422);
        }

        ReportedAuction::create([
            'auction_id' => $auction->id,
            'reporter_id' => $request->user()->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report submitted. Our team will review it.',
        ]);
    }
}
