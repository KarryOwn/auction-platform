<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\EscrowHold;
use App\Services\EscrowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Safety-net job: finds active escrow holds on auctions that are
 * already completed or cancelled, and releases them.
 *
 * Runs daily to catch any data inconsistencies.
 */
class CleanupStaleEscrowHolds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(EscrowService $escrowService): void
    {
        // Holds that are still active but the auction is no longer active
        $staleHolds = EscrowHold::where('status', EscrowHold::STATUS_ACTIVE)
            ->whereHas('auction', function ($query) {
                $query->whereIn('status', [Auction::STATUS_COMPLETED, Auction::STATUS_CANCELLED]);
            })
            ->with('auction')
            ->get();

        if ($staleHolds->isEmpty()) {
            return;
        }

        Log::warning('CleanupStaleEscrowHolds: found stale holds', [
            'count' => $staleHolds->count(),
        ]);

        foreach ($staleHolds as $hold) {
            try {
                $escrowService->refundHold($hold);

                Log::info('CleanupStaleEscrowHolds: released stale hold', [
                    'hold_id'    => $hold->id,
                    'user_id'    => $hold->user_id,
                    'auction_id' => $hold->auction_id,
                    'amount'     => $hold->amount,
                ]);
            } catch (\Throwable $e) {
                Log::error('CleanupStaleEscrowHolds: failed to release hold', [
                    'hold_id' => $hold->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
