<?php

use App\Events\AuctionClosed;
use App\Listeners\HandleAuctionClosed;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\AuctionLostNotification;
use App\Notifications\AuctionWonNotification;
use App\Notifications\PaymentCapturedNotification;
use App\Services\EscrowService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('auction close listener notifies winner and losing bidder', function () {
    Notification::fake();

    $seller = createSeller();
    $winner = User::factory()->create();
    $loser = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_COMPLETED,
        'winner_id' => $winner->id,
        'winning_bid_amount' => 200,
        'current_price' => 200,
        'bid_count' => 2,
        'closed_at' => now(),
    ]);

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $winner->id, 'amount' => 200]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $loser->id, 'amount' => 195]);

    $invoice = Invoice::factory()->create([
        'auction_id' => $auction->id,
        'buyer_id' => $winner->id,
        'seller_id' => $seller->id,
    ]);

    $paymentMock = Mockery::mock(PaymentService::class);
    $paymentMock->shouldReceive('captureWinnerPayment')->once()->andReturn($invoice);
    $paymentMock->shouldReceive('calculateSellerAmount')->andReturn(190.0);
    $paymentMock->shouldReceive('calculatePlatformFee')->andReturn(10.0);
    app()->instance(PaymentService::class, $paymentMock);

    $escrowMock = Mockery::mock(EscrowService::class);
    $escrowMock->shouldReceive('releaseAllForAuction')->once()->andReturnNull();
    app()->instance(EscrowService::class, $escrowMock);

    app(HandleAuctionClosed::class)->handle(new AuctionClosed($auction->fresh()));

    Notification::assertSentTo($winner, AuctionWonNotification::class);
    Notification::assertSentTo($winner, PaymentCapturedNotification::class);
    Notification::assertSentTo($loser, AuctionLostNotification::class);
});
