<?php

namespace App\Jobs;

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Services\Bidding\PendingRedisBidStore;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchPersistRedisBids implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [1, 3, 10];
    public int $uniqueFor = 30;

    public function __construct(
        public int $auctionId,
        public int $limit = 100,
    ) {
        $this->onConnection((string) config('auction.bids_queue.connection', 'redis'));
        $this->onQueue('bids');
    }

    public function uniqueId(): string
    {
        return (string) $this->auctionId;
    }

    public function handle(PendingRedisBidStore $pendingBids): void
    {
        $pendingBids->clearDrainScheduled($this->auctionId);

        $payloads = $pendingBids->pendingBidsForAuction($this->auctionId, $this->limit);

        if ($payloads === []) {
            return;
        }

        usort($payloads, static function (array $first, array $second): int {
            return ((float) ($first['accepted_at'] ?? 0)) <=> ((float) ($second['accepted_at'] ?? 0));
        });

        $created = DB::transaction(function () use ($payloads) {
            $acceptedIds = collect($payloads)->pluck('accepted_bid_id')->filter()->map(fn ($id) => (string) $id)->all();
            $existingAcceptedIds = Bid::whereIn('accepted_bid_id', $acceptedIds)
                ->lockForUpdate()
                ->pluck('accepted_bid_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $existingAccepted = array_flip($existingAcceptedIds);
            $auction = Auction::lockForUpdate()->findOrFail($this->auctionId);
            $userIds = collect($payloads)->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->all();
            $existingUserIds = Bid::where('auction_id', $this->auctionId)
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $seenUsers = array_fill_keys($existingUserIds, true);
            $created = [];
            $maxAmount = (float) $auction->current_price;
            $snipeBids = 0;

            foreach ($payloads as $payload) {
                $acceptedBidId = (string) ($payload['accepted_bid_id'] ?? '');

                if ($acceptedBidId === '' || isset($existingAccepted[$acceptedBidId])) {
                    continue;
                }

                $bid = new Bid([
                    'accepted_bid_id' => $acceptedBidId,
                    'auction_id' => $this->auctionId,
                    'user_id' => (int) $payload['user_id'],
                    'amount' => (float) $payload['amount'],
                    'bid_type' => $payload['bid_type'] ?? Bid::TYPE_MANUAL,
                    'previous_amount' => $payload['previous_amount'] ?? null,
                    'ip_address' => $payload['ip_address'] ?? '127.0.0.1',
                    'user_agent' => $payload['user_agent'] ?? 'RedisEngine/Batch',
                    'auto_bid_id' => ($payload['auto_bid_id'] ?? '') === '' ? null : $payload['auto_bid_id'],
                    'is_snipe_bid' => (bool) ($payload['is_snipe_bid'] ?? false),
                ]);

                if ($acceptedAt = $this->acceptedAt($payload['accepted_at'] ?? null)) {
                    $bid->created_at = $acceptedAt;
                    $bid->updated_at = $acceptedAt;
                }

                $bid->save();
                $created[] = $bid;
                $maxAmount = max($maxAmount, (float) $bid->amount);

                if (! isset($seenUsers[$bid->user_id])) {
                    $seenUsers[$bid->user_id] = true;
                    $auction->unique_bidder_count++;
                }

                if ($bid->is_snipe_bid) {
                    $snipeBids++;
                }
            }

            if ($created !== []) {
                $auction->bid_count += count($created);

                if ((float) $auction->current_price < $maxAmount) {
                    $auction->current_price = $maxAmount;
                }

                if ($auction->hasReserve() && ! $auction->reserve_met && $auction->isReserveMet()) {
                    $auction->reserve_met = true;
                }

                while ($snipeBids > 0 && $auction->isInSnipeWindow() && $auction->canExtend()) {
                    $auction->end_time = $auction->end_time->addSeconds($auction->snipe_extension_seconds);
                    $auction->extension_count++;
                    $snipeBids--;
                }

                $auction->save();
                $auction->refresh();
            }

            return collect($created)
                ->map(fn (Bid $bid) => ['bid' => $bid->refresh(), 'auction' => $auction])
                ->all();
        });

        foreach ($payloads as $payload) {
            $acceptedBidId = (string) ($payload['accepted_bid_id'] ?? '');

            if ($acceptedBidId !== '') {
                $pendingBids->markProcessed($this->auctionId, $acceptedBidId);
            }
        }

        foreach ($created as $row) {
            try {
                BidPlaced::dispatch($row['bid'], $row['auction']);
            } catch (\Throwable $e) {
                Log::error('BatchPersistRedisBids: BidPlaced dispatch failed', [
                    'auction_id' => $this->auctionId,
                    'bid_id' => $row['bid']->id,
                    'accepted_bid_id' => $row['bid']->accepted_bid_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($pendingBids->pendingCount($this->auctionId) > 0) {
            self::dispatch($this->auctionId, $this->limit)
                ->onConnection((string) config('auction.bids_queue.connection', 'redis'))
                ->onQueue('bids');
        }
    }

    private function acceptedAt(mixed $acceptedAt): ?CarbonImmutable
    {
        if (! is_numeric($acceptedAt)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((string) $acceptedAt);
    }
}
