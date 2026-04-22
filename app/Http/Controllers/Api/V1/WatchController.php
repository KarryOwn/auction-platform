<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionWatcher;
use App\Support\ApiAbilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auctions/{auction}/watch",
     *     summary="Toggle watch status for an auction",
     *     tags={"Watchlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="auction", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="outbid_threshold", type="number", nullable=true),
     *             @OA\Property(property="price_alert_at", type="number", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Watchlist status toggled or updated")
     * )
     */
    public function toggle(Request $request, Auction $auction): JsonResponse
    {
        $this->ensureAbility($request, ApiAbilities::WATCHLIST_WRITE);

        $validated = $request->validate([
            'outbid_threshold' => ['nullable', 'numeric', 'min:0.01'],
            'price_alert_at' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $watcher = AuctionWatcher::query()
            ->where('auction_id', $auction->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($watcher && ($request->has('outbid_threshold') || $request->has('price_alert_at'))) {
            $watcher->update([
                'outbid_threshold_amount' => $validated['outbid_threshold'] ?? null,
                'price_alert_at' => $validated['price_alert_at'] ?? null,
                'price_alert_sent' => false,
            ]);

            return response()->json([
                'watching' => true,
                'message' => 'Watchlist preferences updated.',
            ]);
        }

        if ($watcher) {
            $watcher->delete();

            return response()->json([
                'watching' => false,
                'message' => 'Removed from watchlist.',
            ]);
        }

        AuctionWatcher::create([
            'auction_id' => $auction->id,
            'user_id' => $request->user()->id,
            'notify_outbid' => true,
            'notify_ending' => true,
            'notify_cancelled' => true,
            'outbid_threshold_amount' => $validated['outbid_threshold'] ?? null,
            'price_alert_at' => $validated['price_alert_at'] ?? null,
        ]);

        return response()->json([
            'watching' => true,
            'message' => 'Added to watchlist!',
        ]);
    }

    private function ensureAbility(Request $request, string $ability): void
    {
        if (! $request->user()->tokenCan($ability)) {
            throw ApiException::forbidden("Token lacks {$ability} scope.");
        }
    }
}
