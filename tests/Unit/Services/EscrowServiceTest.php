<?php

use App\Models\Auction;
use App\Models\EscrowHold;
use App\Models\User;
use App\Services\EscrowService;

test('hold for bid creates and updates escrow hold incrementally', function () {
    $user = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 0]);
    $auction = Auction::factory()->create();
    $service = app(EscrowService::class);

    $service->holdForBid($user, $auction, 100);
    $service->holdForBid($user, $auction, 140);

    $hold = EscrowHold::where('user_id', $user->id)
        ->where('auction_id', $auction->id)
        ->first();

    expect($hold)->not->toBeNull();
    expect($hold->amount)->toBe('140.00');
    expect($user->fresh()->held_balance)->toBe('140.00');
});

test('release for user marks hold released', function () {
    $user = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 100]);
    $auction = Auction::factory()->create();

    EscrowHold::create([
        'user_id' => $user->id,
        'auction_id' => $auction->id,
        'amount' => 100,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);

    app(EscrowService::class)->releaseForUser($user, $auction);

    $hold = EscrowHold::where('user_id', $user->id)
        ->where('auction_id', $auction->id)
        ->first();

    expect($hold->status)->toBe(EscrowHold::STATUS_RELEASED);
    expect($user->fresh()->held_balance)->toBe('0.00');
});
