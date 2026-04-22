<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\BidRetractionRequest;
use App\Models\User;

test('winning active bid shows request retraction action in bid history', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
    ]);

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer->id,
        'amount' => 150,
        'is_retracted' => false,
    ]);

    $this->actingAs($buyer)
        ->get(route('user.bids'))
        ->assertOk()
        ->assertSeeText('Request Retraction');
});

test('pending retraction request appears in admin queue', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Retractable Lot',
        'status' => Auction::STATUS_ACTIVE,
    ]);

    $bid = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer->id,
        'amount' => 220,
    ]);

    BidRetractionRequest::create([
        'bid_id' => $bid->id,
        'user_id' => $buyer->id,
        'auction_id' => $auction->id,
        'reason' => 'Placed one zero too many.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bid-retractions.index'))
        ->assertOk()
        ->assertSeeText('Bid Retractions')
        ->assertSeeText('Retractable Lot')
        ->assertSeeText('Placed one zero too many.')
        ->assertSeeText('Approve')
        ->assertSeeText('Decline');
});
