<?php

use App\Jobs\CloseExpiredAuctions;
use App\Models\Auction;
use App\Models\Conversation;
use App\Models\User;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('closing job marks expired auction as completed with winner', function () {
    $seller = createSeller();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);

    $auction = createActiveAuction($seller, [
        'current_price' => 100,
        'starting_price' => 100,
        'min_bid_increment' => 5,
        'end_time' => now()->addMinutes(10),
    ]);

    $this->actingAs($bidder)->postJson(route('auctions.bid', $auction), ['amount' => 105])->assertOk();

    $auction->update(['end_time' => now()->subSecond()]);

    CloseExpiredAuctions::dispatchSync();

    $auction->refresh();

    expect($auction->status)->toBe(Auction::STATUS_COMPLETED)
        ->and($auction->winner_id)->toBe($bidder->id)
        ->and((float) $auction->winning_bid_amount)->toBe(105.0);

    $conversation = Conversation::query()
        ->where('auction_id', $auction->id)
        ->where('buyer_id', $bidder->id)
        ->where('seller_id', $seller->id)
        ->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->delivery_status)->toBe(Conversation::DELIVERY_PENDING);
});

test('closing job completes expired auction without winner when no bids', function () {
    $seller = createSeller();
    $auction = createActiveAuction($seller, ['end_time' => now()->subSecond()]);

    CloseExpiredAuctions::dispatchSync();

    $auction->refresh();

    expect($auction->status)->toBe(Auction::STATUS_COMPLETED)
        ->and($auction->winner_id)->toBeNull();

    expect(Conversation::query()->where('auction_id', $auction->id)->exists())->toBeFalse();
});

test('closing job reuses existing buyer seller conversation for won auction delivery', function () {
    $seller = createSeller();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);

    $auction = createActiveAuction($seller, [
        'current_price' => 100,
        'starting_price' => 100,
        'min_bid_increment' => 5,
        'end_time' => now()->addMinutes(10),
    ]);

    $existing = Conversation::create([
        'auction_id' => $auction->id,
        'buyer_id' => $bidder->id,
        'seller_id' => $seller->id,
        'last_message_at' => now()->subHour(),
    ]);

    $this->actingAs($bidder)->postJson(route('auctions.bid', $auction), ['amount' => 105])->assertOk();

    $auction->update(['end_time' => now()->subSecond()]);

    CloseExpiredAuctions::dispatchSync();

    $existing->refresh();

    expect(Conversation::query()->where('auction_id', $auction->id)->where('buyer_id', $bidder->id)->count())->toBe(1)
        ->and($existing->delivery_status)->toBe(Conversation::DELIVERY_PENDING)
        ->and($existing->is_closed)->toBeFalse();
});
