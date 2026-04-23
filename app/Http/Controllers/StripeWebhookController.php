<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\PayoutPaidNotification;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature invalid', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Connected account ID for Connect webhooks (e.g., payout.paid, payout.failed)
        $connectedAccountId = $request->header('Stripe-Account');

        match ($event->type) {
            'checkout.session.completed'  => $this->handleCheckoutCompleted($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'account.updated'             => $this->handleAccountUpdated($event->data->object),
            'transfer.created'            => null, // acknowledged, no action needed
            'payout.paid'                 => $this->handlePayoutPaid($event->data->object, $connectedAccountId),
            'payout.failed'               => $this->handlePayoutFailed($event->data->object, $connectedAccountId),
            default                       => null,
        };

        return response()->json(['received' => true]);
    }

    protected function handleCheckoutCompleted(\Stripe\Checkout\Session $session): void
    {
        // Idempotency guard — skip if already processed
        $alreadyProcessed = WalletTransaction::where('stripe_session_id', $session->id)->exists();
        if ($alreadyProcessed) {
            Log::info('Stripe webhook: duplicate session, skipping', ['session_id' => $session->id]);
            return;
        }

        $userId = (int) ($session->client_reference_id ?? $session->metadata->user_id ?? 0);
        $user   = User::find($userId);

        if (! $user) {
            Log::error('Stripe webhook: user not found', ['user_id' => $userId]);
            return;
        }

        $amountUsd = (float) ($session->amount_total / 100); // cents → dollars

        $tx = $this->walletService->deposit(
            $user,
            $amountUsd,
            'Wallet top-up via Stripe',
        );

        // Store the Stripe session ID for idempotency & audit
        $tx->update([
            'stripe_session_id'        => $session->id,
            'stripe_payment_intent_id' => $session->payment_intent,
        ]);

        Log::info('Stripe webhook: wallet credited', [
            'user_id'    => $user->id,
            'amount'     => $amountUsd,
            'session_id' => $session->id,
        ]);
    }

    protected function handlePaymentFailed(\Stripe\PaymentIntent $intent): void
    {
        Log::warning('Stripe payment failed', [
            'payment_intent' => $intent->id,
            'user_metadata'  => $intent->metadata->toArray(),
        ]);
        // Optionally notify user via notification
    }

    protected function handleAccountUpdated(\Stripe\Account $account): void
    {
        $user = User::where('stripe_connect_account_id', $account->id)->first();
        if (! $user) return;

        if ($account->charges_enabled && ! $user->stripe_connect_onboarded) {
            $user->update(['stripe_connect_onboarded' => true]);
            Log::info('StripeWebhook: connect account onboarded', ['user_id' => $user->id]);
        }
    }

    protected function handlePayoutPaid(\Stripe\Payout $payout, ?string $accountId): void
    {
        if (! $accountId) return;

        $user = User::where('stripe_connect_account_id', $accountId)->first();
        if (! $user) return;

        $amount = (float) ($payout->amount / 100);

        // Deduct from wallet_balance and release hold simultaneously
        DB::transaction(function () use ($user, $amount, $payout) {
            $locked = User::lockForUpdate()->find($user->id);
            $locked->decrement('wallet_balance', $amount);
            $locked->decrement('held_balance', $amount);
            $locked->decrement('pending_payout_balance', min($amount, (float) $locked->pending_payout_balance));
            $locked->refresh();

            WalletTransaction::create([
                'user_id'       => $user->id,
                'type'          => WalletTransaction::TYPE_WITHDRAWAL,
                'amount'        => $amount,
                'balance_after' => $locked->wallet_balance,
                'description'   => 'Withdrawal paid — bank transfer complete',
                'stripe_session_id' => $payout->id,
            ]);
        });

        $user->notify(new PayoutPaidNotification($amount));

        Log::info('StripeWebhook: payout paid, wallet deducted', [
            'user_id' => $user->id,
            'amount'  => $amount,
        ]);
    }

    protected function handlePayoutFailed(\Stripe\Payout $payout, ?string $accountId): void
    {
        if (! $accountId) return;

        $user = User::where('stripe_connect_account_id', $accountId)->first();
        if (! $user) return;

        $amount = (float) ($payout->amount / 100);

        // Release the hold — money stays in wallet
        $this->walletService->release(
            $user,
            $amount,
            'Withdrawal hold released — payout failed',
        );
        $user->decrement('pending_payout_balance', min($amount, (float) $user->fresh()->pending_payout_balance));

        Log::warning('StripeWebhook: payout failed, hold released', [
            'user_id' => $user->id,
            'amount'  => $amount,
        ]);
    }
}
