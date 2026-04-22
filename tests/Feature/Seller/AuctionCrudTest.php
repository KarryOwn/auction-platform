<?php

use App\Models\Auction;
use App\Models\User;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('verified seller can access auction crud index', function () {
    $seller = createSeller();

    $this->actingAs($seller)
        ->get(route('seller.auctions.index'))
        ->assertOk();
});

test('non seller is redirected from seller routes', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($user)
        ->get(route('seller.auctions.index'))
        ->assertRedirect(route('seller.apply.form'));
});

test('seller can clone auction from seller crud area', function () {
    $seller = createSeller();
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_COMPLETED,
        'start_time' => now()->subDays(2),
        'end_time' => now()->subDay(),
    ]);

    $response = $this->actingAs($seller)
        ->post(route('seller.auctions.clone', $auction));

    $response->assertRedirect();

    expect(Auction::where('cloned_from_auction_id', $auction->id)->exists())->toBeTrue();
});
