<?php

use App\Models\Auction;
use Illuminate\Support\Facades\Cache;

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

test('featured scope only includes active auctions', function () {
    $active = Auction::factory()->featured()->create([
        'title' => 'Active featured auction',
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
    ]);

    Auction::factory()->featured()->cancelled()->create([
        'title' => 'Cancelled featured auction',
        'end_time' => now()->addHour(),
    ]);

    Auction::factory()->featured()->create([
        'title' => 'Expired active featured auction',
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->subMinute(),
    ]);

    expect(Auction::featured()->pluck('id')->all())->toBe([$active->id]);
});

test('featured auctions cache is cleared when a featured auction is cancelled', function () {
    $auction = Auction::factory()->featured()->create([
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
    ]);

    Cache::put('featured_auctions', collect([$auction]), 300);

    $auction->update(['status' => Auction::STATUS_CANCELLED]);

    expect(Cache::has('featured_auctions'))->toBeFalse();
});
