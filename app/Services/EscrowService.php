<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\EscrowHold;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EscrowService
{
    public function __construct(
        protected WalletService $walletService,
    ) {}

    /**
     * Hold funds for a bid. If the user already holds funds on this auction,
     * only the incremental difference is held (bids only go up).
     */
    public function holdForBid(User $user, Auction $auction, float $bidAmount): EscrowHold
    {
        return DB::transaction(function () use ($user, $auction, $bidAmount) {
            // Find any existing active hold for this user on this auction
            $existingHold = EscrowHold::where('user_id', $user->id)
                ->where('auction_id', $auction->id)
                ->where('status', EscrowHold::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            $existingAmount  = $existingHold ? (float) $existingHold->amount : 0.0;
            $incrementalCost = round($bidAmount - $existingAmount, 2);

            if ($incrementalCost > 0) {
                // Hold the additional funds
                $this->walletService->hold(
                    $user,
                    $incrementalCost,
                    "Bid hold for auction #{$auction->id}",
                    $auction,
                );
            }

            if ($existingHold) {
                // Update existing hold to new total amount
                $existingHold->update(['amount' => $bidAmount]);
                return $existingHold;
            }

            // Create new hold record
            return EscrowHold::create([
                'user_id'    => $user->id,
                'auction_id' => $auction->id,
                'amount'     => $bidAmount,
                'status'     => EscrowHold::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * Release held funds for a specific user on an auction (e.g., when outbid).
     */
    public function releaseForUser(User $user, Auction $auction): void
    {
        DB::transaction(function () use ($user, $auction) {
            $hold = EscrowHold::where('user_id', $user->id)
                ->where('auction_id', $auction->id)
                ->where('status', EscrowHold::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $hold) {
                return;
            }

            $this->walletService->release(
                $user,
                (float) $hold->amount,
                "Bid released — outbid on auction #{$auction->id}",
                $auction,
            );

            $hold->markReleased();

            Log::info('EscrowService: released hold', [
                'user_id'    => $user->id,
                'auction_id' => $auction->id,
                'amount'     => $hold->amount,
            ]);
        });
    }

    /**
     * Capture the winner's held funds as payment.
     */
    public function captureForWinner(User $user, Auction $auction): EscrowHold
    {
        return DB::transaction(function () use ($user, $auction) {
            $hold = EscrowHold::where('user_id', $user->id)
                ->where('auction_id', $auction->id)
                ->where('status', EscrowHold::STATUS_ACTIVE)
                ->lockForUpdate()
                ->firstOrFail();

            $this->walletService->captureHold(
                $user,
                (float) $hold->amount,
                "Payment captured for auction #{$auction->id}: {$auction->title}",
                $auction,
            );

            $hold->markCaptured();

            Log::info('EscrowService: captured hold for winner', [
                'user_id'    => $user->id,
                'auction_id' => $auction->id,
                'amount'     => $hold->amount,
            ]);

            return $hold;
        });
    }

    /**
     * Release all active holds for an auction (e.g., on close or cancel).
     * Optionally exclude a specific user (the winner).
     */
    public function releaseAllForAuction(Auction $auction, ?int $excludeUserId = null): void
    {
        $query = EscrowHold::where('auction_id', $auction->id)
            ->where('status', EscrowHold::STATUS_ACTIVE);

        if ($excludeUserId) {
            $query->where('user_id', '!=', $excludeUserId);
        }

        $holds = $query->get();

        foreach ($holds as $hold) {
            DB::transaction(function () use ($hold, $auction) {
                $hold = EscrowHold::lockForUpdate()->find($hold->id);
                if (! $hold || ! $hold->isActive()) {
                    return;
                }

                $user = User::find($hold->user_id);
                if (! $user) {
                    return;
                }

                $this->walletService->release(
                    $user,
                    (float) $hold->amount,
                    "Bid released — auction #{$auction->id} ended",
                    $auction,
                );

                $hold->markReleased();
            });
        }

        Log::info('EscrowService: released all holds for auction', [
            'auction_id'     => $auction->id,
            'count'          => $holds->count(),
            'excluded_user'  => $excludeUserId,
        ]);
    }

    /**
     * Refund a captured hold (for cancelled/refunded auctions).
     */
    public function refundHold(EscrowHold $hold): void
    {
        DB::transaction(function () use ($hold) {
            $hold = EscrowHold::lockForUpdate()->findOrFail($hold->id);
            $user = User::findOrFail($hold->user_id);
            $auction = Auction::find($hold->auction_id);

            if ($hold->status === EscrowHold::STATUS_CAPTURED) {
                // Captured funds need to be refunded to wallet
                $this->walletService->refund(
                    $user,
                    (float) $hold->amount,
                    "Refund for auction #{$hold->auction_id}",
                    $auction,
                );
            } elseif ($hold->status === EscrowHold::STATUS_ACTIVE) {
                // Active hold just needs to be released
                $this->walletService->release(
                    $user,
                    (float) $hold->amount,
                    "Hold released — auction #{$hold->auction_id} refunded",
                    $auction,
                );
            }

            $hold->markRefunded();

            Log::info('EscrowService: refunded hold', [
                'hold_id'    => $hold->id,
                'user_id'    => $user->id,
                'auction_id' => $hold->auction_id,
                'amount'     => $hold->amount,
            ]);
        });
    }

    /**
     * Get the current active hold amount for a user on an auction.
     */
    public function getActiveHoldAmount(User $user, Auction $auction): float
    {
        $hold = EscrowHold::where('user_id', $user->id)
            ->where('auction_id', $auction->id)
            ->where('status', EscrowHold::STATUS_ACTIVE)
            ->first();

        return $hold ? (float) $hold->amount : 0.0;
    }
}
