<?php

use App\Models\Auction;
use App\Models\User;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('bid in snipe window extends auction end time', function () {
    $seller = createSeller();
    $bidder = User::factory()->create(['wallet_balance' => 500]);

    $auction = createActiveAuction($seller, [
        'current_price' => 100,
        'starting_price' => 100,
        'min_bid_increment' => 5,
        'end_time' => now()->addSeconds(20),
        'snipe_threshold_seconds' => 30,
        'snipe_extension_seconds' => 60,
        'max_extensions' => 2,
        'extension_count' => 0,
    ]);

    $beforeEnd = $auction->end_time->copy();

    $response = $this->actingAs($bidder)
        ->postJson(route('auctions.bid', $auction), ['amount' => 105]);

    $response->assertOk();

    $auction->refresh();
    expect($auction->extension_count)->toBe(1);
    expect($auction->end_time->gt($beforeEnd))->toBeTrue();
});
