<?php

use App\Models\Auction;
use App\Models\EscrowHold;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('full refund updates payment and invoice status', function () {
    Notification::fake();

    $seller = User::factory()->create(['wallet_balance' => 190]);
    $buyer = User::factory()->create(['wallet_balance' => 300]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'winner_id' => $buyer->id,
        'status' => Auction::STATUS_COMPLETED,
        'current_price' => 200,
        'winning_bid_amount' => 200,
        'payment_status' => 'paid',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => Invoice::generateNumber(),
        'auction_id' => $auction->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'subtotal' => 200,
        'platform_fee' => 10,
        'seller_amount' => 190,
        'total' => 200,
        'currency' => 'USD',
        'status' => Invoice::STATUS_PAID,
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    EscrowHold::create([
        'user_id' => $buyer->id,
        'auction_id' => $auction->id,
        'amount' => 200,
        'status' => EscrowHold::STATUS_CAPTURED,
        'captured_at' => now(),
    ]);

    app(App\Services\RefundService::class)->refundAuctionPayment($auction, 'Test refund');

    expect($auction->fresh()->payment_status)->toBe('refunded');
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_REFUNDED);

    Notification::assertSentTo($buyer, RefundProcessedNotification::class);
});
