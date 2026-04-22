<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\BiddingStrategy;
use App\Exceptions\ApiException;
use App\Exceptions\BidValidationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BidResource;
use App\Models\Auction;
use App\Support\ApiAbilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BidController extends Controller
{
    public function __construct(
        protected BiddingStrategy $engine,
    ) {}

    public function index(Request $request, Auction $auction): AnonymousResourceCollection
    {
        $this->ensureAbility($request, ApiAbilities::BIDS_READ);

        $bids = $auction->bids()
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 15), 50))
            ->withQueryString();

        return BidResource::collection($bids);
    }

    public function store(Request $request, Auction $auction): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->ensureAbility($request, ApiAbilities::BIDS_PLACE);

        try {
            $bid = $this->engine->placeBid(
                $auction,
                $request->user(),
                (float) $validated['amount'],
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            return response()->json([
                'data' => new BidResource($bid),
                'meta' => [
                    'new_price' => (float) $bid->amount,
                ],
            ], 201);
        } catch (BidValidationException $exception) {
            return response()->json([
                'error' => $exception->getErrorCode(),
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'details' => $exception->getContext(),
            ], $exception->getCode());
        }
    }

    private function ensureAbility(Request $request, string $ability): void
    {
        if (! $request->user()->tokenCan($ability)) {
            throw ApiException::forbidden("Token lacks {$ability} scope.");
        }
    }
}
