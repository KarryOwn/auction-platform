<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Services\BuyItNowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyItNowController extends Controller
{
    public function __construct(protected BuyItNowService $service) {}

    public function purchase(Request $request, Auction $auction): JsonResponse
    {
        try {
            $invoice = $this->service->purchase($auction, $request->user());
            return response()->json([
                'success'    => true,
                'message'    => 'Purchase successful! You won this auction.',
                'invoice_id' => $invoice->id,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
