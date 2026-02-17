<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Services\PriceSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(private readonly PriceSuggestionService $service) {}

    public function suggestPrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $data = $this->service->suggest($validated['title'], $validated['description'] ?? null);

        return response()->json(['data' => $data]);
    }

    public function auctionInsights(Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);

        $insights = $this->service->auctionInsights($auction);

        return view('seller.insights.show', compact('auction', 'insights'));
    }
}
