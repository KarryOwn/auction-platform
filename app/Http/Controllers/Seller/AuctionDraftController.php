<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuctionDraftController extends Controller
{
    /**
     * Auto-save a draft — accepts partial data, no validation failures returned as errors.
     * Only operates on DRAFT auctions owned by the authenticated seller.
     */
    public function autoSave(Request $request, Auction $auction): JsonResponse
    {
        if ($auction->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if (! $auction->isDraft()) {
            return response()->json(['error' => 'Only drafts can be auto-saved.'], 422);
        }

        // Whitelist of fields safe for auto-save
        $allowed = [
            'title', 'description', 'video_url', 'condition', 'brand_id',
            'sku', 'serial_number', 'reserve_price_visible',
            'snipe_threshold_seconds', 'snipe_extension_seconds', 'max_extensions',
        ];

        $data = Arr::only($request->all(), $allowed);

        if (! empty($data)) {
            $auction->fill($data);
            $auction->auto_saved_at = now();
            $auction->saveQuietly(); // skip model events to avoid cache flushes
        }

        return response()->json([
            'saved'         => true,
            'auto_saved_at' => $auction->auto_saved_at?->toIso8601String(),
        ]);
    }
}
