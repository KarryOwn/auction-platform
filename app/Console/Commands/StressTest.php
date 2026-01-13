<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use App\Models\Auction;

class StressTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stress:test {auction_id} {count}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stress test auction bidding endpoint with many concurrent bids';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $auctionId = $this->argument('auction_id');
        $count = $this->argument('count');
        $auction = Auction::find($auctionId);

        $this->info("🚀 LAUNCHING STRESS TEST: {$count} bots targeting Auction #{$auctionId}...");
        $this->info("Current Price: $" . $auction->current_price);

        $startTime = microtime(true);

        // Use Http::pool to send requests in parallel (simultaneously)
        $responses = Http::pool(function (Pool $pool) use ($count, $auctionId, $auction) {
            $requests = [];
            for ($i = 0; $i < $count; $i++) {
                $bidAmount = $auction->current_price + 5 + $i; 
                
                $requests[] = $pool->post('http://localhost/api/stress-test/bid', [
                    'secret' => 'thesis-2026',
                    'auction_id' => $auctionId,
                    'amount' => $bidAmount,
                ]);
            }
            return $requests;
        });

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Analyze Results
        $success = 0;
        $fails = 0;

        foreach ($responses as $response) {
            if ($response->successful()) {
                $success++;
            } else {
                $fails++;
            }
        }

        $this->newLine();
        $this->info("--- REPORT ---");
        $this->info("Time Taken: " . number_format($duration, 2) . " seconds");
        $this->info("Successful Bids: " . $success);
        $this->error("Failed/Blocked Bids: " . $fails);
        $this->info("Requests per Second: " . number_format($count / $duration, 2));
    }
}
