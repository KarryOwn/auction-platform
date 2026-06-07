<?php

namespace App\Jobs;

use App\Jobs\BatchPersistRedisBids;
use App\Services\Bidding\PendingRedisBidStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcilePendingRedisBids implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $olderThanSeconds = 10,
    ) {
        $this->onConnection((string) config('auction.bids_queue.connection', 'redis'));
        $this->onQueue('bids');
    }

    public function handle(PendingRedisBidStore $pendingBids): void
    {
        $auctionIds = $pendingBids->auctionsWithPendingBids($this->olderThanSeconds);

        foreach ($auctionIds as $auctionId) {
            try {
                BatchPersistRedisBids::dispatch(
                    auctionId: $auctionId,
                    limit: (int) config('auction.redis_persistence.batch_size', 100),
                )
                    ->onConnection((string) config('auction.bids_queue.connection', 'redis'))
                    ->onQueue('bids');
            } catch (\Throwable $e) {
                Log::error('ReconcilePendingRedisBids: pending bid recovery failed', [
                    'auction_id' => $auctionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
