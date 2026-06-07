<?php

namespace App\Jobs;

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
        foreach ($pendingBids->duePendingBids($this->olderThanSeconds) as $payload) {
            $acceptedBidId = (string) ($payload['accepted_bid_id'] ?? '');

            if ($acceptedBidId === '') {
                continue;
            }

            try {
                (new ProcessWinningBid(
                    auctionId: (int) $payload['auction_id'],
                    userId: (int) $payload['user_id'],
                    amount: (float) $payload['amount'],
                    meta: [
                        'bid_type' => $payload['bid_type'] ?? 'manual',
                        'previous_amount' => $payload['previous_amount'] ?? null,
                        'ip_address' => $payload['ip_address'] ?? '127.0.0.1',
                        'user_agent' => $payload['user_agent'] ?? 'RedisEngine/Reconciler',
                        'auto_bid_id' => ($payload['auto_bid_id'] ?? '') === '' ? null : (int) $payload['auto_bid_id'],
                        'is_snipe_bid' => $payload['is_snipe_bid'] ?? false,
                        'accepted_at' => $payload['accepted_at'] ?? null,
                    ],
                    acceptedBidId: $acceptedBidId,
                ))->handle();
            } catch (\Throwable $e) {
                Log::error('ReconcilePendingRedisBids: pending bid recovery failed', [
                    'auction_id' => $payload['auction_id'] ?? null,
                    'user_id' => $payload['user_id'] ?? null,
                    'amount' => $payload['amount'] ?? null,
                    'accepted_bid_id' => $acceptedBidId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
