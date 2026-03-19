<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
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

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            default => null,
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
}
