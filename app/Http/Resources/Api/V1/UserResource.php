<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'seller_slug' => $this->seller_slug,
            'seller_verified' => $this->isVerifiedSeller(),
            'wallet_balance' => $this->when(
                $request->boolean('include_wallet'),
                fn () => (float) $this->wallet_balance
            ),
            'held_balance' => $this->when(
                $request->boolean('include_wallet'),
                fn () => (float) $this->held_balance
            ),
            'available_balance' => $this->when(
                $request->boolean('include_wallet'),
                fn () => $this->availableBalance()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
