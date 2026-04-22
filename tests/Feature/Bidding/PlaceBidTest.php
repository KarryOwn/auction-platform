<?php

use App\Models\Auction;
use App\Models\User;
use App\Services\Bidding\PessimisticSqlEngine;
use App\Contracts\BiddingStrategy;

// Use PessimisticSqlEngine for all bidding tests (no Redis required in CI)
beforeEach(function () {
    app()->bind(BiddingStrategy::class, PessimisticSqlEngine::class);
});

test('user can place a valid bid', function () {
    $seller = User::factory()->create(['wallet_balance' => 0]);
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id'         => $seller->id,
        'starting_price'  => 100,
        'current_price'   => 100,
        'min_bid_increment' => 5,
        'status'          => Auction::STATUS_ACTIVE,
        'end_time'        => now()->addHour(),
    ]);

    $response = $this->actingAs($bidder)->postJson(
        route('auctions.bid', $auction),
        ['amount' => 105]
    );

    $response->assertOk()->assertJson(['success' => true]);
    expect($auction->fresh()->current_price)->toBe('105.00');
});

test('bid below minimum is rejected', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100, 'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE, 'end_time' => now()->addHour(),
    ]);

    $response = $this->actingAs($bidder)->postJson(route('auctions.bid', $auction), ['amount' => 104]);

    $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
});

test('seller cannot bid on own auction', function () {
    $seller = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100,
        'status' => Auction::STATUS_ACTIVE, 'end_time' => now()->addHour(),
    ]);

    $response = $this->actingAs($seller)->postJson(route('auctions.bid', $auction), ['amount' => 105]);

    $response->assertStatus(422)->assertJsonValidationErrors(['auction']);
});

test('bid is rejected when auction has ended', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->subSecond(), // Already ended
    ]);

    $response = $this->actingAs($bidder)->postJson(route('auctions.bid', $auction), ['amount' => 105]);

    $response->assertStatus(422)->assertJsonValidationErrors(['auction']);
});

test('bid escrow hold is created when bid is placed', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 0]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100, 'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE, 'end_time' => now()->addHour(),
    ]);

    $this->actingAs($bidder)->postJson(route('auctions.bid', $auction), ['amount' => 105]);

    $bidder->refresh();
    expect($bidder->held_balance)->toBe('105.00');
    expect(\App\Models\EscrowHold::where('user_id', $bidder->id)->where('auction_id', $auction->id)->exists())->toBeTrue();
});

test('outbid user escrow is released when outbid', function () {
    $seller  = User::factory()->create();
    $bidder1 = User::factory()->create(['wallet_balance' => 500]);
    $bidder2 = User::factory()->create(['wallet_balance' => 500]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100, 'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE, 'end_time' => now()->addHour(),
    ]);

    $engine = app(BiddingStrategy::class);
    $engine->placeBid($auction, $bidder1, 105, ['ip_address' => '127.0.0.1']);

    // Process the HandleBidPlaced listener synchronously
    \Illuminate\Support\Facades\Event::fake([\App\Events\BidPlaced::class]);
    $engine->placeBid($auction->fresh(), $bidder2, 110, ['ip_address' => '127.0.0.1']);

    // Simulate listener
    $bid2 = \App\Models\Bid::where('user_id', $bidder2->id)->latest()->first();
    app(\App\Listeners\HandleBidPlaced::class)->handle(new \App\Events\BidPlaced(
        $bid2, $auction->fresh()
    ));

    $bidder1->refresh();
    expect($bidder1->held_balance)->toBe('0.00');
});