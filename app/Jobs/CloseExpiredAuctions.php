<?php

namespace App\Jobs;

use App\Contracts\BiddingStrategy;
use App\Events\AuctionClosed;
use App\Models\Auction;
use App\Models\AuctionSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled job that finds all active auctions past their end_time
 * and transitions them to completed status.
 *
 * Intended to run every minute via the scheduler.
 */
class CloseExpiredAuctions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(BiddingStrategy $engine): void
    {
        $expired = Auction::where('status', Auction::STATUS_ACTIVE)
            ->where('end_time', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        Log::info("CloseExpiredAuctions: closing {$expired->count()} auction(s)");

        foreach ($expired as $auction) {
            try {
                $this->closeAuction($auction, $engine);
            } catch (\Throwable $e) {
                Log::error('CloseExpiredAuctions: failed to close', [
                    'auction_id' => $auction->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    protected function closeAuction(Auction $auction, BiddingStrategy $engine): void
    {
        // Guard: if the engine (Redis) reports a higher price than the DB,
        // there are still unprocessed bids in the queue.  Defer this close
        // to the next scheduler run so ProcessWinningBid can finish first.
        $enginePrice = $engine->getCurrentPrice($auction);
        $dbPrice     = (float) $auction->current_price;

        if ($enginePrice > $dbPrice) {
            Log::warning("CloseExpiredAuctions: deferring auction #{$auction->id} — pending bids (engine: {$enginePrice}, DB: {$dbPrice})");
            return;
        }

        DB::transaction(function () use ($auction, $engine) {
            $auction = Auction::lockForUpdate()->findOrFail($auction->id);

            // Double-check it's still active
            if ($auction->status !== Auction::STATUS_ACTIVE) {
                return;
            }

            // Determine winner (highest bid)
            $winningBid = $auction->bids()->orderByDesc('amount')->first();

            $auction->status    = Auction::STATUS_COMPLETED;
            $auction->closed_at = now();

            if ($winningBid) {
                // Only set winner if reserve is met (or no reserve)
                if ($auction->isReserveMet()) {
                    $auction->winner_id          = $winningBid->user_id;
                    $auction->winning_bid_amount = $winningBid->amount;
                }
            }

            $auction->save();

            // Final snapshot
            AuctionSnapshot::capture($auction, ['trigger' => 'close']);

            // Cleanup engine resources
            $engine->cleanup($auction);
        });

        // Dispatch domain event outside transaction — wrap in try-catch
        // so a broadcast failure doesn't prevent processing other auctions.
        try {
            AuctionClosed::dispatch($auction->fresh());
        } catch (\Throwable $e) {
            Log::error("CloseExpiredAuctions: AuctionClosed broadcast failed (auction #{$auction->id} closed OK)", [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("CloseExpiredAuctions: closed auction #{$auction->id}", [
            'winner_id' => $auction->winner_id,
            'final_price' => $auction->current_price,
        ]);
    }
}
