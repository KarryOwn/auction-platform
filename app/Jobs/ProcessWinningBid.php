<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessWinningBid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $auctionId;
    public $userId;
    public $amount;

    public function __construct($auctionId, $userId, $amount)
    {
        $this->auctionId = $auctionId;
        $this->userId = $userId;
        $this->amount = $amount;
    }

    public function handle(): void
    {
        DB::transaction(function () {
            //  Update Auction Price
            $auction = Auction::find($this->auctionId);
            
            // Safety check: Only update if DB is actually behind
            if ($auction->current_price < $this->amount) {
                $auction->current_price = $this->amount;
                $auction->save();
            }

            //  Record the Bid History
            Bid::create([
                'auction_id' => $this->auctionId,
                'user_id' => $this->userId,
                'amount' => $this->amount,
                'ip_address' => '127.0.0.1', // Simplified for background job
                'user_agent' => 'RedisEngine',
            ]);
        });
    }
}