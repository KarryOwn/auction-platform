<?php

use App\Models\User;

test('wallet top up increases user wallet balance', function () {
    $user = User::factory()->create(['wallet_balance' => 100]);

    $response = $this->actingAs($user)->post(route('user.wallet.top-up'), [
        'amount' => 50,
    ]);

    $response->assertRedirect(route('user.wallet'));
    expect($user->fresh()->wallet_balance)->toBe('150.00');
});

test('wallet withdraw validates available balance', function () {
    $user = User::factory()->create(['wallet_balance' => 20, 'held_balance' => 10]);

    $response = $this->actingAs($user)->post(route('user.wallet.withdraw'), [
        'amount' => 15,
    ]);

    $response->assertSessionHasErrors('amount');
});
