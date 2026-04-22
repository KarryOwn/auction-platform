<?php

use App\Models\Auction;
use App\Models\User;

test('payment is captured from winners escrow on auction close', function () {
    $seller = User::factory()->create(['wallet_balance' => 0]);
    $winner = User::factory()->create(['wallet_balance' => 500]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 200, 'status' => Auction::STATUS_COMPLETED,
        'winner_id' => $winner->id, 'winning_bid_amount' => 200,
    ]);

    // Create escrow hold
    \App\Models\EscrowHold::create([
        'user_id' => $winner->id, 'auction_id' => $auction->id,
        'amount' => 200, 'status' => 'active',
    ]);

    $winner->update(['held_balance' => 200]);

    app(\App\Services\PaymentService::class)->captureWinnerPayment($auction);

    $winner->refresh();
    $seller->refresh();

    expect($winner->wallet_balance)->toBe('300.00') // 500 - 200
        ->and($winner->held_balance)->toBe('0.00')
        ->and($seller->wallet_balance)->toBeGreaterThan(0);
});
