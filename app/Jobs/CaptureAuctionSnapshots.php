<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\AuctionSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Periodic job that captures price/bid snapshots of all active auctions.
 * Used for analytics, price-history charts, and auditing.
 *
 * Intended to run every 1–5 minutes via the scheduler.
 */
class CaptureAuctionSnapshots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function handle(): void
    {
        $actives = Auction::where('status', Auction::STATUS_ACTIVE)
            ->where('end_time', '>', now())
            ->get();

        if ($actives->isEmpty()) {
            return;
        }

        $captured = 0;

        foreach ($actives as $auction) {
            try {
                AuctionSnapshot::capture($auction, ['trigger' => 'scheduled']);
                $captured++;
            } catch (\Throwable $e) {
                Log::warning('CaptureAuctionSnapshots: failed', [
                    'auction_id' => $auction->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info("CaptureAuctionSnapshots: captured {$captured}/{$actives->count()} snapshots");
    }
}
