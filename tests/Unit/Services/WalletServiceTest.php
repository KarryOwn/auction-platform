<?php

use App\Models\User;

test('deposit credits wallet balance', function () {
    $user    = User::factory()->create(['wallet_balance' => 100]);
    $service = app(\App\Services\WalletService::class);

    $tx = $service->deposit($user, 50.00, 'Test deposit');

    $user->refresh();
    expect($user->wallet_balance)->toBe('150.00')
        ->and($tx->type)->toBe('deposit')
        ->and($tx->amount)->toBe('50.00')
        ->and($tx->balance_after)->toBe('150.00');
});

test('withdraw fails when insufficient balance', function () {
    $user    = User::factory()->create(['wallet_balance' => 50, 'held_balance' => 30]);
    $service = app(\App\Services\WalletService::class);

    expect(fn () => $service->withdraw($user, 30.00, 'Test'))
        ->toThrow(\InvalidArgumentException::class);
    // Available balance is 50 - 30 = 20, which is < 30
});

test('hold reduces available balance', function () {
    $user    = User::factory()->create(['wallet_balance' => 200, 'held_balance' => 0]);
    $service = app(\App\Services\WalletService::class);

    $service->hold($user, 100, 'Bid hold');

    $user->refresh();
    expect($user->held_balance)->toBe('100.00')
        ->and($user->availableBalance())->toBe(100.0);
});
