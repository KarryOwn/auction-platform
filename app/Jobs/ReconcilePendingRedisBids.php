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
    ) {}

    public function handle(PendingRedisBidStore $pendingBids): void
    {
        $auctionIds = collect($pendingBids->duePendingBids($this->olderThanSeconds))
            ->pluck('auction_id')
            ->map(fn ($auctionId) => (int) $auctionId)
            ->filter()
            ->unique()
            ->values();

        foreach ($auctionIds as $auctionId) {
            try {
                (new BatchPersistRedisBids($auctionId))->handle($pendingBids);
            } catch (\Throwable $e) {
                Log::error('ReconcilePendingRedisBids: pending bid recovery failed', [
                    'auction_id' => $auctionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
