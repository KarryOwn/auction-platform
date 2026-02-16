<?php

namespace App\Jobs;

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persists a Redis-accepted bid into PostgreSQL (eventual consistency)
 * and dispatches the BidPlaced domain event for downstream listeners.
 */
class ProcessWinningBid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public array $backoff = [1, 5, 15];

    public function __construct(
        public int   $auctionId,
        public int   $userId,
        public float $amount,
        public array $meta = [],
    ) {}

    public function handle(): void
    {
        // Persist bid inside transaction; dispatch broadcast event AFTER commit
        // so a broadcast failure (e.g. Reverb down) never rolls back the DB write.
        $result = DB::transaction(function () {
            $auction = Auction::lockForUpdate()->findOrFail($this->auctionId);

            $previousPrice = (float) $auction->current_price;

            // Only update if DB is actually behind Redis
            if ($previousPrice < $this->amount) {
                $auction->current_price = $this->amount;

                // Check reserve
                if ($auction->hasReserve() && ! $auction->reserve_met && $auction->isReserveMet()) {
                    $auction->reserve_met = true;
                }

                $auction->save();
            }

            // Create the persistent bid record
            $bid = Bid::create([
                'auction_id'      => $this->auctionId,
                'user_id'         => $this->userId,
                'amount'          => $this->amount,
                'bid_type'        => $this->meta['bid_type'] ?? Bid::TYPE_MANUAL,
                'previous_amount' => $this->meta['previous_amount'] ?? $previousPrice,
                'ip_address'      => $this->meta['ip_address'] ?? '127.0.0.1',
                'user_agent'      => $this->meta['user_agent'] ?? 'RedisEngine/Background',
                'auto_bid_id'     => $this->meta['auto_bid_id'] ?? null,
                'is_snipe_bid'    => $this->meta['is_snipe_bid'] ?? false,
            ]);

            // Increment counters
            $auction->incrementBidCounters($this->userId);

            // Anti-snipe extension (check from DB side too)
            if ($bid->is_snipe_bid) {
                $auction->applySnipeExtension();
            }

            Log::info('ProcessWinningBid: persisted', [
                'bid_id'     => $bid->id,
                'auction_id' => $this->auctionId,
                'amount'     => $this->amount,
            ]);

            return ['bid' => $bid, 'auction' => $auction];
        });

        // Dispatch domain event AFTER the transaction has committed so
        // a broadcast failure (Reverb/Pusher down) can never roll back the DB write.
        try {
            BidPlaced::dispatch($result['bid'], $result['auction']);
        } catch (\Throwable $e) {
            Log::error('ProcessWinningBid: BidPlaced broadcast failed (bid persisted OK)', [
                'auction_id' => $this->auctionId,
                'amount'     => $this->amount,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessWinningBid FAILED', [
            'auction_id' => $this->auctionId,
            'user_id'    => $this->userId,
            'amount'     => $this->amount,
            'error'      => $exception->getMessage(),
        ]);
    }
}