<?php

use App\Events\BidPlaced;
use App\Jobs\ProcessWinningBid;
use App\Jobs\ReconcilePendingRedisBids;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Services\Bidding\PendingRedisBidStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

test('process winning bid is idempotent for a redis accepted bid', function () {
    Event::fake([BidPlaced::class]);

    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'current_price' => 100,
        'starting_price' => 100,
        'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
    ]);

    $job = new ProcessWinningBid(
        auctionId: $auction->id,
        userId: $bidder->id,
        amount: 105,
        meta: [
            'previous_amount' => 100,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'is_snipe_bid' => false,
        ],
        acceptedBidId: 'accepted-bid-1',
    );

    $job->handle();
    $job->handle();

    expect(Bid::where('accepted_bid_id', 'accepted-bid-1')->count())->toBe(1);
    expect($auction->fresh()->current_price)->toBe('105.00');

    Event::assertDispatchedTimes(BidPlaced::class, 1);
});

test('process winning bid preserves redis accepted timestamp seconds', function () {
    Event::fake([BidPlaced::class]);

    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'current_price' => 100,
        'starting_price' => 100,
        'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
    ]);
    $acceptedAt = Carbon::parse('2026-06-06 10:00:00.654321');

    (new ProcessWinningBid(
        auctionId: $auction->id,
        userId: $bidder->id,
        amount: 105,
        meta: [
            'previous_amount' => 100,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'is_snipe_bid' => false,
            'accepted_at' => $acceptedAt->format('U.u'),
        ],
        acceptedBidId: 'accepted-bid-with-time',
    ))->handle();

    $bid = Bid::where('accepted_bid_id', 'accepted-bid-with-time')->firstOrFail();

    expect($bid->created_at->format('Y-m-d H:i:s'))->toBe($acceptedAt->format('Y-m-d H:i:s'));
});

test('reconciler persists pending redis accepted bids', function () {
    Event::fake([BidPlaced::class]);

    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'current_price' => 100,
        'starting_price' => 100,
        'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
    ]);

    $pendingBids = Mockery::mock(PendingRedisBidStore::class);
    $pendingBids->shouldReceive('duePendingBids')
        ->once()
        ->with(10)
        ->andReturn([[
            'accepted_bid_id' => 'accepted-bid-2',
            'auction_id' => $auction->id,
            'user_id' => $bidder->id,
            'amount' => 110,
            'bid_type' => Bid::TYPE_MANUAL,
            'previous_amount' => 100,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'auto_bid_id' => null,
            'is_snipe_bid' => false,
        ]]);
    $pendingBids->shouldReceive('markProcessed')
        ->once()
        ->with($auction->id, 'accepted-bid-2');

    app()->instance(PendingRedisBidStore::class, $pendingBids);

    (new ReconcilePendingRedisBids)->handle($pendingBids);

    expect(Bid::where('accepted_bid_id', 'accepted-bid-2')->count())->toBe(1);
    expect($auction->fresh()->current_price)->toBe('110.00');

    Event::assertDispatchedTimes(BidPlaced::class, 1);
});
