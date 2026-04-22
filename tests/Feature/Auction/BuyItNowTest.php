<?php

use App\Models\Auction;
use App\Models\User;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('buyer can complete buy it now purchase', function () {
    $seller = createSeller(['wallet_balance' => 0]);
    $buyer = User::factory()->create(['wallet_balance' => 1000, 'held_balance' => 0]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'current_price' => 100,
        'buy_it_now_enabled' => true,
        'buy_it_now_price' => 200,
        'buy_it_now_expires_at' => null,
    ]);

    $response = $this->actingAs($buyer)
        ->postJson(route('auctions.buy-it-now', $auction));

    $response->assertOk()->assertJson(['success' => true]);

    $auction->refresh();
    expect($auction->status)->toBe(Auction::STATUS_COMPLETED)
        ->and($auction->winner_id)->toBe($buyer->id);
});

test('seller cannot use buy it now on own auction', function () {
    $seller = createSeller(['wallet_balance' => 1000]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'current_price' => 100,
        'buy_it_now_enabled' => true,
        'buy_it_now_price' => 200,
        'buy_it_now_expires_at' => null,
    ]);

    $response = $this->actingAs($seller)
        ->postJson(route('auctions.buy-it-now', $auction));

    $response->assertStatus(422)->assertJson(['success' => false]);
});
