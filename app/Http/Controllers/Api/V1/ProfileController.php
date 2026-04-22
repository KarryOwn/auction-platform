<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BidResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\ApiAbilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        $this->ensureAbility($request, ApiAbilities::PROFILE_READ);

        $request->merge(['include_wallet' => false]);

        return new UserResource($request->user());
    }

    public function bids(Request $request): AnonymousResourceCollection
    {
        $this->ensureAbility($request, ApiAbilities::BIDS_READ);

        $bids = $request->user()->bids()
            ->with('auction:id,title,status')
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 15), 50))
            ->withQueryString();

        return BidResource::collection($bids);
    }

    public function wallet(Request $request): JsonResponse
    {
        $this->ensureAbility($request, ApiAbilities::WALLET_READ);

        $user = $request->user();
        $transactions = $user->walletTransactions()
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 15), 50))
            ->withQueryString();

        return response()->json([
            'data' => [
                'wallet_balance' => (float) $user->wallet_balance,
                'held_balance' => (float) $user->held_balance,
                'available_balance' => $user->availableBalance(),
            ],
            'transactions' => $transactions,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $this->ensureAbility($request, ApiAbilities::PROFILE_READ);

        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 15), 50))
            ->through(fn ($notification) => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);

        return response()->json($notifications);
    }

    private function ensureAbility(Request $request, string $ability): void
    {
        if (! $request->user()->tokenCan($ability)) {
            throw ApiException::forbidden("Token lacks {$ability} scope.");
        }
    }
}
