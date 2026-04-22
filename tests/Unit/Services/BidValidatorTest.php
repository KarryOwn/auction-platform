<?php

use App\Exceptions\BidValidationException;
use App\Models\Auction;
use App\Models\User;
use App\Services\Bidding\BidValidator;

test('bid validator rejects self bidding', function () {
    $seller = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
        'current_price' => 100,
        'min_bid_increment' => 5,
    ]);

    $validator = app(BidValidator::class);

    expect(fn () => $validator->validate($auction, $seller, 105))
        ->toThrow(BidValidationException::class, 'You cannot bid on your own auction.');
});

test('bid validator rejects amount below minimum next bid', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
        'current_price' => 100,
        'min_bid_increment' => 5,
    ]);

    $validator = app(BidValidator::class);

    expect(fn () => $validator->validate($auction, $bidder, 101))
        ->toThrow(BidValidationException::class, 'Bid must be at least');
});
