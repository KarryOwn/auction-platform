<?php

use App\Events\BidPlaced;
use App\Listeners\HandleBidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\OutbidNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('previous highest bidder receives outbid notification', function () {
    Notification::fake();

    $seller = createSeller();
    $bidder1 = User::factory()->create();
    $bidder2 = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'current_price' => 110,
        'min_bid_increment' => 5,
    ]);

    Bid::create([
        'auction_id' => $auction->id,
        'user_id' => $bidder1->id,
        'amount' => 105,
        'bid_type' => Bid::TYPE_MANUAL,
        'previous_amount' => 100,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
    ]);

    $newBid = Bid::create([
        'auction_id' => $auction->id,
        'user_id' => $bidder2->id,
        'amount' => 110,
        'bid_type' => Bid::TYPE_MANUAL,
        'previous_amount' => 105,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
    ]);

    app(HandleBidPlaced::class)->handle(new BidPlaced($newBid, $auction->fresh()));

    Notification::assertSentTo($bidder1, OutbidNotification::class);
});
