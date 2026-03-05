<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    /**
     * Deposit funds into a user's wallet.
     */
    public function deposit(User $user, float $amount, string $description = 'Deposit', ?Model $reference = null): WalletTransaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            $user->increment('wallet_balance', $amount);
            $user->refresh();

            return $this->recordTransaction(
                $user,
                WalletTransaction::TYPE_DEPOSIT,
                $amount,
                $description,
                $reference,
            );
        });
    }

    /**
     * Withdraw funds from a user's available (non-held) balance.
     */
    public function withdraw(User $user, float $amount, string $description = 'Withdrawal'): WalletTransaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $description) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            $available = $user->availableBalance();
            if ($available < $amount) {
                throw new InvalidArgumentException(
                    "Insufficient available balance. Available: \${$available}, requested: \${$amount}"
                );
            }

            $user->decrement('wallet_balance', $amount);
            $user->refresh();

            return $this->recordTransaction(
                $user,
                WalletTransaction::TYPE_WITHDRAWAL,
                $amount,
                $description,
            );
        });
    }

    /**
     * Hold funds for an escrow (bid deposit).
     * Moves amount from available to held.
     */
    public function hold(User $user, float $amount, string $description = 'Bid hold', ?Model $reference = null): WalletTransaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            $available = $user->availableBalance();
            if ($available < $amount) {
                throw new InvalidArgumentException(
                    "Insufficient available balance for hold. Available: \${$available}, requested: \${$amount}"
                );
            }

            $user->increment('held_balance', $amount);
            $user->refresh();

            return $this->recordTransaction(
                $user,
                WalletTransaction::TYPE_BID_HOLD,
                $amount,
                $description,
                $reference,
            );
        });
    }

    /**
     * Release held funds back to available balance.
     */
    public function release(User $user, float $amount, string $description = 'Bid release', ?Model $reference = null): WalletTransaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            // Held balance should always be >= amount, but guard against negative
            $releaseAmount = min($amount, (float) $user->held_balance);
            if ($releaseAmount <= 0) {
                throw new InvalidArgumentException('No held balance to release.');
            }

            $user->decrement('held_balance', $releaseAmount);
            $user->refresh();

            return $this->recordTransaction(
                $user,
                WalletTransaction::TYPE_BID_RELEASE,
                $releaseAmount,
                $description,
                $reference,
            );
        });
    }

    /**
     * Capture held funds as payment.
     * Reduces both wallet_balance and held_balance.
     */
    public function captureHold(User $user, float $amount, string $description = 'Payment captured', ?Model $reference = null): WalletTransaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            $captureAmount = min($amount, (float) $user->held_balance);
            if ($captureAmount <= 0) {
                throw new InvalidArgumentException('No held balance to capture.');
            }

            $user->decrement('wallet_balance', $captureAmount);
            $user->decrement('held_balance', $captureAmount);
            $user->refresh();

            return $this->recordTransaction(
                $user,
                WalletTransaction::TYPE_PAYMENT,
                $captureAmount,
                $description,
                $reference,
            );
        });
    }

    /**
     * Refund funds to a user's wallet.
     */
    public function refund(User $user, float $amount, string $description = 'Refund', ?Model $reference = null): WalletTransaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            $user->increment('wallet_balance', $amount);
            $user->refresh();

            return $this->recordTransaction(
                $user,
                WalletTransaction::TYPE_REFUND,
                $amount,
                $description,
                $reference,
            );
        });
    }

    /**
     * Credit seller's wallet (auction payout).
     */
    public function creditSeller(User $user, float $amount, string $description = 'Seller payout', ?Model $reference = null): WalletTransaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            $user->increment('wallet_balance', $amount);
            $user->refresh();

            return $this->recordTransaction(
                $user,
                WalletTransaction::TYPE_SELLER_CREDIT,
                $amount,
                $description,
                $reference,
            );
        });
    }

    // ── Internals ──────────────────────────────

    protected function recordTransaction(
        User $user,
        string $type,
        float $amount,
        string $description,
        ?Model $reference = null,
    ): WalletTransaction {
        $data = [
            'user_id'       => $user->id,
            'type'          => $type,
            'amount'        => $amount,
            'balance_after' => $user->wallet_balance,
            'description'   => $description,
        ];

        if ($reference) {
            $data['reference_type'] = $reference->getMorphClass();
            $data['reference_id']   = $reference->getKey();
        }

        return WalletTransaction::create($data);
    }

    protected function assertPositive(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Amount must be positive, got: {$amount}");
        }
    }
}
