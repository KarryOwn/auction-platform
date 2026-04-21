<?php

namespace App\Services;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VacationModeService
{
    public function __construct(protected BiddingStrategy $biddingStrategy) {}

    /**
     * Activate vacation mode for a seller.
     * Options:
     *  - 'pause': extend all active auction end_times; no new bids accepted (via status check)
     *  - 'message_only': keep auctions running but show "seller is away" banner
     */
    public function activate(User $seller, ?Carbon $endsAt, string $message, string $mode = 'pause'): void
    {
        DB::transaction(function () use ($seller, $endsAt, $message, $mode) {
            $seller->update([
                'vacation_mode'            => true,
                'vacation_mode_started_at' => now(),
                'vacation_mode_ends_at'    => $endsAt,
                'vacation_mode_message'    => $message,
            ]);

            if ($mode === 'pause') {
                $this->pauseActiveAuctions($seller);
            }
        });

        Log::info('VacationModeService: activated', [
            'seller_id' => $seller->id,
            'ends_at'   => $endsAt?->toIso8601String(),
        ]);
    }

    /**
     * Deactivate vacation mode and restore auction end times.
     */
    public function deactivate(User $seller): void
    {
        DB::transaction(function () use ($seller) {
            $seller->update([
                'vacation_mode'            => false,
                'vacation_mode_started_at' => null,
                'vacation_mode_ends_at'    => null,
                'vacation_mode_message'    => null,
            ]);

            $this->resumePausedAuctions($seller);
        });

        Log::info('VacationModeService: deactivated', ['seller_id' => $seller->id]);
    }

    private function pauseActiveAuctions(User $seller): void
    {
        $active = Auction::where('user_id', $seller->id)
            ->where('status', Auction::STATUS_ACTIVE)
            ->where('paused_by_vacation', false)
            ->get();

        foreach ($active as $auction) {
            $auction->update([
                'original_end_time'  => $auction->end_time,
                'paused_by_vacation' => true,
                'paused_at'          => now(),
                'end_time'           => now()->addYear(),
                'ending_soon_notified' => false,
            ]);
        }
    }

    private function resumePausedAuctions(User $seller): void
    {
        $paused = Auction::where('user_id', $seller->id)
            ->where('paused_by_vacation', true)
            ->get();

        foreach ($paused as $auction) {
            $vacationDuration = now()->diffInSeconds($auction->paused_at);
            $newEndTime = $auction->original_end_time->addSeconds($vacationDuration);

            $auction->update([
                'end_time'           => $newEndTime,
                'original_end_time'  => null,
                'paused_by_vacation' => false,
                'paused_at'          => null,
            ]);
        }
    }

    /**
     * Auto-deactivate vacation mode when vacation_mode_ends_at is reached.
     * Called by scheduler.
     */
    public function autoDeactivateExpired(): void
    {
        $sellers = User::where('vacation_mode', true)
            ->whereNotNull('vacation_mode_ends_at')
            ->where('vacation_mode_ends_at', '<=', now())
            ->get();

        foreach ($sellers as $seller) {
            $this->deactivate($seller);
        }
    }
}
