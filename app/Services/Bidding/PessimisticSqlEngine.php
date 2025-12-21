<?php

namespace App\Services\Bidding;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\User;
use App\Models\Bid;
use Illuminate\Support\Facades\DB;
use Exception;

class PessimisticSqlEngine implements BiddingStrategy
{
    public function placeBid(Auction $auction, User $user, float $amount): Bid
    {
        // Start a Database Transaction
        return DB::transaction(function () use ($auction, $user, $amount) {
            
            // 1. LOCK the row. No one else can read this auction until we finish.
            // This is the "Pessimistic" part.
            $lockedAuction = Auction::where('id', $auction->id)
                                    ->lockForUpdate()
                                    ->first();

            // 2. Validation Logic (The "Business Rules")
            if ($lockedAuction->status !== 'active') {
                throw new Exception("Auction is not active.");
            }

            if ($lockedAuction->end_time < now()) {
                throw new Exception("Auction has ended.");
            }

            if ($amount <= $lockedAuction->current_price) {
                throw new Exception("Bid must be higher than {$lockedAuction->current_price}.");
            }

            // 3. Update the Price
            $lockedAuction->current_price = $amount;
            $lockedAuction->save();

            // 4. Create the Bid Record
            return Bid::create([
                'auction_id' => $lockedAuction->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'ip_address' => request()->ip(),
            ]);
        });
    }
}