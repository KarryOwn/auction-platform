<?php

use App\Models\Auction;
use App\Models\User;

test('api returns active auctions', function () {
    $seller = User::factory()->create();
    Auction::factory()->count(3)->create(['user_id' => $seller->id, 'status' => 'active', 'end_time' => now()->addHour()]);
    Auction::factory()->count(2)->create(['user_id' => $seller->id, 'status' => 'completed']);

    $response = $this->getJson('/api/v1/auctions');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'title', 'current_price', 'status']], 'links', 'meta']);
});

test('api requires token to place bid', function () {
    $auction = Auction::factory()->create(['status' => 'active', 'end_time' => now()->addHour()]);

    $response = $this->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount' => 100]);

    $response->assertStatus(401);
});

test('api bid respects token abilities', function () {
    $user    = User::factory()->create(['wallet_balance' => 1000]);
    $seller  = User::factory()->create();
    $auction = Auction::factory()->create(['user_id' => $seller->id, 'status' => 'active', 'end_time' => now()->addHour(), 'current_price' => 100, 'min_bid_increment' => 5]);

    // Token without bid ability
    $token = $user->createToken('test', ['auctions:read'])->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount' => 105]);

    $response->assertStatus(403);
});
