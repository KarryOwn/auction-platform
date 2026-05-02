<?php

use App\Models\User;
use App\Models\WalletTransaction;

function signedStripePayload(array $payload, string $secret): array
{
    $json = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$json, $secret);

    return [
        $json,
        "t={$timestamp},v1={$signature}",
    ];
}

test('stripe checkout completed webhook credits wallet and records transaction', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $user = User::factory()->create(['wallet_balance' => 100]);

    [$payload, $signature] = signedStripePayload([
        'id' => 'evt_wallet_top_up',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_wallet_top_up',
                'object' => 'checkout.session',
                'client_reference_id' => (string) $user->id,
                'metadata' => ['user_id' => (string) $user->id],
                'amount_total' => 50000,
                'payment_status' => 'paid',
                'payment_intent' => 'pi_wallet_top_up',
            ],
        ],
    ], 'whsec_test');

    $this->withHeader('Stripe-Signature', $signature)
        ->postJson(route('stripe.webhook'), json_decode($payload, true))
        ->assertOk()
        ->assertJson(['received' => true]);

    $user->refresh();
    expect((float) $user->wallet_balance)->toBe(600.0);

    $transaction = WalletTransaction::query()->where('stripe_session_id', 'cs_wallet_top_up')->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe(WalletTransaction::TYPE_DEPOSIT)
        ->and((float) $transaction->amount)->toBe(500.0)
        ->and((float) $transaction->balance_after)->toBe(600.0)
        ->and($transaction->stripe_payment_intent_id)->toBe('pi_wallet_top_up');
});

test('stripe checkout completed webhook is idempotent for duplicate sessions', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $user = User::factory()->create(['wallet_balance' => 100]);

    [$payload, $signature] = signedStripePayload([
        'id' => 'evt_wallet_top_up_duplicate',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_wallet_top_up_duplicate',
                'object' => 'checkout.session',
                'client_reference_id' => (string) $user->id,
                'metadata' => ['user_id' => (string) $user->id],
                'amount_total' => 50000,
                'payment_status' => 'paid',
                'payment_intent' => 'pi_wallet_top_up_duplicate',
            ],
        ],
    ], 'whsec_test');

    $request = fn () => $this->withHeader('Stripe-Signature', $signature)
        ->postJson(route('stripe.webhook'), json_decode($payload, true));

    $request()->assertOk();
    $request()->assertOk();

    $user->refresh();
    expect((float) $user->wallet_balance)->toBe(600.0)
        ->and(WalletTransaction::where('stripe_session_id', 'cs_wallet_top_up_duplicate')->count())->toBe(1);
});
