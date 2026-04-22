<?php

use App\Models\Auction;
use App\Models\EscrowHold;
use App\Models\Invoice;
use App\Models\User;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('payment capture marks auction as paid and creates invoice', function () {
    $seller = User::factory()->create(['wallet_balance' => 0]);
    $winner = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 200]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_COMPLETED,
        'winner_id' => $winner->id,
        'winning_bid_amount' => 200,
        'current_price' => 200,
    ]);

    EscrowHold::create([
        'user_id' => $winner->id,
        'auction_id' => $auction->id,
        'amount' => 200,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);

    $invoice = app(App\Services\PaymentService::class)->captureWinnerPayment($auction);

    expect($invoice)->toBeInstanceOf(Invoice::class);
    expect($auction->fresh()->payment_status)->toBe('paid');
    expect($winner->fresh()->held_balance)->toBe('0.00');
});
