<?php

use App\Models\Auction;

test('minimum next bid adds increment to current price', function () {
    $auction = Auction::factory()->create([
        'current_price' => 100,
        'min_bid_increment' => 5,
    ]);

    expect($auction->minimumNextBid())->toBe(105.0);
});

test('auction applies snipe extension inside snipe window', function () {
    $auction = Auction::factory()->create([
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addSeconds(20),
        'snipe_threshold_seconds' => 30,
        'snipe_extension_seconds' => 60,
        'extension_count' => 0,
        'max_extensions' => 3,
    ]);

    $before = $auction->end_time->copy();

    expect($auction->applySnipeExtension())->toBeTrue();

    $auction->refresh();
    expect($auction->extension_count)->toBe(1)
        ->and($auction->end_time->gt($before))->toBeTrue();
});
