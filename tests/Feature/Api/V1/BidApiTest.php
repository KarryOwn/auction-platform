<?php

use App\Models\Auction;
use App\Models\User;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('api bid endpoint allows bids when token has bids place ability', function () {
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $seller = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'current_price' => 100,
        'min_bid_increment' => 5,
    ]);

    $token = $bidder->createToken('api-bid', ['bids:place'])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount' => 105]);

    $response->assertStatus(201)
        ->assertJsonPath('meta.new_price', 105);
});

test('api bid endpoint rejects token without bids place ability', function () {
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $seller = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'current_price' => 100,
        'min_bid_increment' => 5,
    ]);

    $token = $bidder->createToken('api-no-bid', ['auctions:read'])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount' => 105]);

    $response->assertStatus(403);
});
