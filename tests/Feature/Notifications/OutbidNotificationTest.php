<?php

use App\Events\BidPlaced;
use App\Jobs\SendCoalescedOutbidNotification;
use App\Listeners\HandleBidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\OutbidNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('previous highest bidder receives coalesced outbid notification job', function () {
    Notification::fake();
    Queue::fake();

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

    Notification::assertNothingSent();
    Queue::assertPushed(SendCoalescedOutbidNotification::class, fn ($job) => $job->auctionId === $auction->id
        && $job->userId === $bidder1->id
        && $job->isWatcher === false);
});

test('coalesced outbid job sends latest notification state', function () {
    Notification::fake();

    $bidder = User::factory()->create();
    $auction = Auction::factory()->create([
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'title' => 'Coalesced Auction',
    ]);

    \Illuminate\Support\Facades\Redis::setex(
        SendCoalescedOutbidNotification::stateKey($auction->id, $bidder->id),
        60,
        json_encode([
            'auction_id' => $auction->id,
            'auction_title' => 'Coalesced Auction',
            'outbid_amount' => 250.0,
            'your_amount' => 200.0,
            'is_watcher' => false,
        ], JSON_THROW_ON_ERROR),
    );

    (new SendCoalescedOutbidNotification($auction->id, $bidder->id))->handle();

    Notification::assertSentTo($bidder, OutbidNotification::class, fn ($notification) => $notification->outbidAmount === 250.0
        && $notification->yourAmount === 200.0);
});
