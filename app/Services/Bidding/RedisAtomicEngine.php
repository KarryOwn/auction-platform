<?php

namespace App\Services\Bidding;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\User;
use App\Models\Bid;
use App\Events\PriceUpdated;
use App\Jobs\ProcessWinningBid;
use Illuminate\Support\Facades\Redis;
use Exception;

class RedisAtomicEngine implements BiddingStrategy
{
    public function placeBid(Auction $auction, User $user, float $amount): Bid
    {
        $key = "auction:{$auction->id}:price";

        // LUA SCRIPT: The "Atomic Guard"
        // It checks the price and updates it in ONE step. No race conditions possible.
        // Returns: 1 (Success) or 0 (Fail)
        $script = <<<LUA
            local current_price = tonumber(redis.call('get', KEYS[1]))
            if not current_price then
                current_price = 0
            end
            
            local bid_amount = tonumber(ARGV[1])

            if bid_amount > current_price then
                redis.call('set', KEYS[1], bid_amount)
                return 1
            else
                return 0
            end
        LUA;

        // Initialize Redis if empty (First run only)
        if (!Redis::exists($key)) {
            Redis::set($key, $auction->current_price);
        }

        // Execute the script
        $result = Redis::eval($script, 1, $key, $amount);

        if ($result == 0) {
            // Get the current price to show the user why they failed
            $current = Redis::get($key);
            throw new Exception("Bid too low! Current price is {$current}");
        }

        // SUCCESS: Update the "View" immediately (Optimistic UI)
        // We create a "Fake" Bid object to return to the Controller immediately
        $bid = new Bid([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'amount' => $amount,
        ]);
        
        // Update the model instance just for the event (not saved to DB yet)
        $auction->current_price = $amount;
        
        // Fire Real-Time Event (IMMEDIATE)
        PriceUpdated::dispatch($auction);

        // Dispatch Background Job (EVENTUAL CONSISTENCY)
        // This saves to MySQL so we don't lose data if Redis crashes
        ProcessWinningBid::dispatch($auction->id, $user->id, $amount)->onQueue('default');

        return $bid;
    }
}