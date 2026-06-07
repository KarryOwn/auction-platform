<?php

use App\Events\BidPlaced;
use App\Events\PriceUpdated;
use App\Jobs\BatchPersistRedisBids;
use App\Jobs\ProcessWinningBid;
use App\Jobs\ReconcilePendingRedisBids;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Services\Bidding\BidRateLimiter;
use App\Services\Bidding\BidValidator;
use App\Services\Bidding\BiddingEngineHealth;
use App\Services\Bidding\PendingRedisBidStore;
use App\Services\Bidding\RedisAtomicEngine;
use App\Services\EscrowService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

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
    $pendingBids->shouldReceive('pendingBidsForAuction')
        ->once()
        ->with($auction->id, 100)
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
    $pendingBids->shouldReceive('clearDrainScheduled')
        ->once()
        ->with($auction->id);

    app()->instance(PendingRedisBidStore::class, $pendingBids);

    (new ReconcilePendingRedisBids)->handle($pendingBids);

    expect(Bid::where('accepted_bid_id', 'accepted-bid-2')->count())->toBe(1);
    expect($auction->fresh()->current_price)->toBe('110.00');

    Event::assertDispatchedTimes(BidPlaced::class, 1);
});

test('batch persistence stores redis accepted bids in accepted order and updates auction once', function () {
    Event::fake([BidPlaced::class]);

    $seller = User::factory()->create();
    $bidderA = User::factory()->create(['wallet_balance' => 1000]);
    $bidderB = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'current_price' => 100,
        'starting_price' => 100,
        'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
        'bid_count' => 0,
        'unique_bidder_count' => 0,
    ]);

    $pendingBids = Mockery::mock(PendingRedisBidStore::class);
    $pendingBids->shouldReceive('clearDrainScheduled')
        ->twice()
        ->with($auction->id);
    $pendingBids->shouldReceive('pendingBidsForAuction')
        ->twice()
        ->with($auction->id, 100)
        ->andReturn([
            [
                'accepted_bid_id' => 'accepted-bid-b',
                'auction_id' => $auction->id,
                'user_id' => $bidderB->id,
                'amount' => 110,
                'bid_type' => Bid::TYPE_MANUAL,
                'previous_amount' => 105,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'auto_bid_id' => null,
                'is_snipe_bid' => false,
                'accepted_at' => '1780740000.200000',
            ],
            [
                'accepted_bid_id' => 'accepted-bid-a',
                'auction_id' => $auction->id,
                'user_id' => $bidderA->id,
                'amount' => 105,
                'bid_type' => Bid::TYPE_MANUAL,
                'previous_amount' => 100,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'auto_bid_id' => null,
                'is_snipe_bid' => false,
                'accepted_at' => '1780740000.100000',
            ],
        ]);
    $pendingBids->shouldReceive('markProcessed')->times(4);
    $pendingBids->shouldReceive('pendingCount')
        ->twice()
        ->with($auction->id)
        ->andReturn(0);

    (new BatchPersistRedisBids($auction->id))->handle($pendingBids);
    (new BatchPersistRedisBids($auction->id))->handle($pendingBids);

    expect(Bid::whereIn('accepted_bid_id', ['accepted-bid-a', 'accepted-bid-b'])->count())->toBe(2);
    expect(Bid::where('auction_id', $auction->id)->orderBy('created_at')->pluck('accepted_bid_id')->all())
        ->toBe(['accepted-bid-a', 'accepted-bid-b']);
    expect($auction->fresh()->current_price)->toBe('110.00');
    expect($auction->fresh()->bid_count)->toBe(2);
    expect($auction->fresh()->unique_bidder_count)->toBe(2);

    Event::assertDispatchedTimes(BidPlaced::class, 2);
});

test('redis accept path initializes missing price inside lua without separate exists call', function () {
    Bus::fake();
    Event::fake([PriceUpdated::class]);

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

    $validator = Mockery::mock(BidValidator::class);
    $validator->shouldReceive('validate')->once();

    $rateLimiter = Mockery::mock(BidRateLimiter::class);
    $rateLimiter->shouldReceive('check')->once();
    $rateLimiter->shouldReceive('hit')->once();

    $escrow = Mockery::mock(EscrowService::class);
    $escrow->shouldReceive('getActiveHoldAmount')->once()->andReturn(0.0);
    $escrow->shouldReceive('holdForBid')->once();

    Redis::shouldReceive('exists')->never();
    Redis::shouldReceive('set')->once()->andReturn(true);
    $evalArguments = null;
    Redis::shouldReceive('eval')
        ->once()
        ->andReturnUsing(function (mixed ...$arguments) use (&$evalArguments) {
            $evalArguments = $arguments;

            return ['105.00', '100', '1'];
        })
        ->byDefault();

    $engine = new RedisAtomicEngine(
        validator: $validator,
        rateLimiter: $rateLimiter,
        escrowService: $escrow,
        health: app(BiddingEngineHealth::class),
    );

    $bid = $engine->placeBid($auction, $bidder, 105, [
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
    ]);

    expect((float) $bid->amount)->toBe(105.0);
    expect($evalArguments[0])->toContain('ARGV[12]')
        ->and($evalArguments[1])->toBe(5)
        ->and($evalArguments[2])->toBe("auction:{$auction->id}:price")
        ->and($evalArguments[18])->toBe('100.00')
        ->and($evalArguments[20])->toBe('1');

    Bus::assertDispatched(BatchPersistRedisBids::class, 1);
});
