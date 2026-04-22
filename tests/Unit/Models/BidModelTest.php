<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;

test('bid increment is calculated from previous amount', function () {
    $bid = Bid::factory()->create([
        'amount' => 120,
        'previous_amount' => 100,
    ]);

    expect($bid->bidIncrement())->toBe(20.0);
});

test('manual and auto scopes filter bids correctly', function () {
    $auction = Auction::factory()->create();
    $user = User::factory()->create();

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $user->id,
        'bid_type' => Bid::TYPE_MANUAL,
    ]);

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $user->id,
        'bid_type' => Bid::TYPE_AUTO,
    ]);

    expect(Bid::manual()->count())->toBeGreaterThan(0)
        ->and(Bid::auto()->count())->toBeGreaterThan(0);
});
