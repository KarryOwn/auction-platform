<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

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
        $count = (int) $this->argument('count');
        $auction = Auction::find($auctionId);

        if (! $auction) {
            $this->error("Auction #{$auctionId} was not found.");
            return self::FAILURE;
        }

        $this->info("🚀 LAUNCHING STRESS TEST: {$count} bots targeting Auction #{$auctionId}...");
        $this->info("Current Price: $" . $auction->current_price);
        $this->info("Minimum Next Bid: $" . number_format($auction->minimumNextBid(), 2));
        Redis::set("auction:{$auctionId}:price", $auction->current_price); 
        $this->info("✅ Redis synced with MySQL."); 

        $botIds = User::where('email', 'like', 'stress-bot-%@example.test')
            ->where('role', User::ROLE_USER)
            ->where('is_banned', false)
            ->pluck('id')
            ->values();

        if ($botIds->isEmpty()) {
            $this->error('No stress bot accounts found. Run: sail artisan stress:seed-bots 100 --balance=1000000');
            return self::FAILURE;
        }

        $this->info("Using {$botIds->count()} stress bot accounts.");

        $startTime = microtime(true);

        // Use Http::pool to send requests in parallel (simultaneously)
        $responses = Http::pool(function (Pool $pool) use ($count, $auctionId, $auction, $botIds) {
            $requests = [];
            $minimumBid = $auction->minimumNextBid();
            $increment = (float) $auction->min_bid_increment;

            for ($i = 0; $i < $count; $i++) {
                $bidAmount = round($minimumBid + ($increment * $i), 2);
                $botId = $botIds[$i % $botIds->count()];
                
                $requests[] = $pool->post('http://localhost/api/stress-test/bid', [
                    'secret' => 'thesis-2026',
                    'auction_id' => $auctionId,
                    'amount' => $bidAmount,
                    'user_id' => $botId,
                ]);
            }
            return $requests;
        });

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Analyze Results
        $success = 0;
        $fails = 0;
        $failureReasons = [];

        foreach ($responses as $response) {
            if ($response->successful()) {
                $success++;
            } else {
                $fails++;
                $reason = $response->json('error') ?? $response->body() ?: 'Unknown failure';
                $failureReasons[$reason] = ($failureReasons[$reason] ?? 0) + 1;

                if ($fails === 1) {
                    $this->error("FIRST FAILURE DETAILS:");
                    $this->error("Status Code: " . $response->status());
                    $this->error("Body: " . $response->body());
                }
            }
        }

        $this->newLine();
        $this->info("--- REPORT ---");
        $this->info("Time Taken: " . number_format($duration, 2) . " seconds");
        $this->info("Successful Bids: " . $success);
        $this->error("Failed/Blocked Bids: " . $fails);
        $this->info("Requests per Second: " . number_format($count / $duration, 2));

        if ($failureReasons) {
            $this->newLine();
            $this->warn("--- FAILURE SUMMARY ---");
            foreach ($failureReasons as $reason => $total) {
                $this->warn("{$total}x {$reason}");
            }
        }

        return self::SUCCESS;
    }
}
