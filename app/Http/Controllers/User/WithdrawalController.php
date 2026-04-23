<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WithdrawalController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:50000',
        ]);

        $user   = $request->user();
        $amount = (float) $validated['amount'];

        // Guard: must have connected bank
        if (! $user->hasConnectedBank()) {
            return back()->withErrors([
                'amount' => 'You must connect a bank account before withdrawing.',
            ]);
        }

        // Guard: sufficient available balance
        if (! $user->canAfford($amount)) {
            return back()->withErrors([
                'amount' => "Insufficient balance. Available: $"
                    . number_format($user->availableBalance(), 2),
            ]);
        }

        // Hold the funds immediately so concurrent requests can't double-withdraw
        $this->walletService->hold(
            $user,
            $amount,
            "Withdrawal hold — pending payout",
        );
        $user->increment('pending_payout_balance', $amount);

        try {
            // Transfer from your platform account TO the connected account
            $transfer = \Stripe\Transfer::create([
                'amount'             => (int) round($amount * 100), // cents
                'currency'           => 'usd',
                'destination'        => $user->stripe_connect_account_id,
                'description'        => "Wallet withdrawal for user #{$user->id}",
                'metadata'           => [
                    'user_id' => $user->id,
                    'type'    => 'wallet_withdrawal',
                ],
            ]);

            // Stripe automatically triggers a payout from the connected account
            // to their bank within 2 business days (instant with Instant Payouts)
            // The payout.paid webhook will deduct from wallet_balance + release hold

            // Store transfer ID so webhook can match it back to this user
            WalletTransaction::create([
                'user_id'       => $user->id,
                'type'          => WalletTransaction::TYPE_WITHDRAWAL,
                'amount'        => $amount,
                'balance_after' => $user->wallet_balance, // unchanged until webhook
                'description'   => 'Withdrawal — awaiting bank transfer',
                'stripe_session_id' => $transfer->id,    // reuse column for transfer ID
            ]);

            Log::info('WithdrawalController: transfer created', [
                'user_id'     => $user->id,
                'amount'      => $amount,
                'transfer_id' => $transfer->id,
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Release hold if Stripe call fails
            $this->walletService->release(
                $user,
                $amount,
                'Withdrawal hold released — Stripe error',
            );
            $user->decrement('pending_payout_balance', min($amount, (float) $user->fresh()->pending_payout_balance));

            Log::error('WithdrawalController: transfer failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return back()->withErrors([
                'amount' => 'Stripe transfer failed: ' . $e->getMessage(),
            ]);
        }

        return redirect()->route('user.wallet')
            ->with('success', 'Withdrawal initiated! Funds will arrive in 1-2 business days.');
    }
}
