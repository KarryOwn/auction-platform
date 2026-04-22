<?php

use App\Models\Auction;
use App\Models\EscrowHold;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PaymentService;

test('payment service calculates platform fee and seller amount', function () {
    $service = app(PaymentService::class);

    expect($service->calculatePlatformFee(100.0))->toBe(5.0)
        ->and($service->calculateSellerAmount(100.0))->toBe(95.0);
});

test('payment service capture returns invoice and marks auction paid', function () {
    $seller = User::factory()->create(['wallet_balance' => 0]);
    $winner = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 200]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'winner_id' => $winner->id,
        'status' => Auction::STATUS_COMPLETED,
        'winning_bid_amount' => 200,
        'current_price' => 200,
    ]);

    EscrowHold::create([
        'user_id' => $winner->id,
        'auction_id' => $auction->id,
        'amount' => 200,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);

    $invoice = app(PaymentService::class)->captureWinnerPayment($auction);

    expect($invoice)->toBeInstanceOf(Invoice::class);
    expect($auction->fresh()->payment_status)->toBe('paid');
});
