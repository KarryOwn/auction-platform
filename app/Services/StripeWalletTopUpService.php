<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class StripeWalletTopUpService
{
    public function process(object $session, ?User $expectedUser = null): WalletTransaction
    {
        $sessionId = (string) data_get($session, 'id');
        $userId = (int) (data_get($session, 'client_reference_id') ?: data_get($session, 'metadata.user_id'));

        if ($expectedUser && $expectedUser->id !== $userId) {
            throw new AuthorizationException('Checkout session does not belong to this user.');
        }

        return DB::transaction(function () use ($session, $sessionId, $userId) {
            $user = User::lockForUpdate()->findOrFail($userId);

            $existing = WalletTransaction::where('stripe_session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $amountUsd = round(((float) data_get($session, 'amount_total')) / 100, 2);

            if ($amountUsd <= 0) {
                throw new \InvalidArgumentException('Stripe Checkout session has no payable amount.');
            }

            $user->increment('wallet_balance', $amountUsd);
            $user->refresh();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => WalletTransaction::TYPE_DEPOSIT,
                'amount' => $amountUsd,
                'balance_after' => $user->wallet_balance,
                'description' => 'Wallet top-up via Stripe',
                'stripe_session_id' => $sessionId,
                'stripe_payment_intent_id' => data_get($session, 'payment_intent'),
            ]);
        });
    }
}
