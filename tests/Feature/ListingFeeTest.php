<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\ListingFeeTier;
use App\Models\User;
use Illuminate\Support\Facades\Config;

test('calculates zero fee by default', function () {
    Config::set('auction.listing_fee.flat', 0.0);
    Config::set('auction.listing_fee.percent', 0.0);

    $seller = User::factory()->create(['role' => 'seller', 'wallet_balance' => 0]);
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
        'starting_price' => 100,
    ]);

    $response = $this->actingAs($seller)->getJson(route('seller.auctions.listing-fee-preview', $auction));

    $response->assertOk()
        ->assertJson(['listing_fee' => 0.0]);
});

test('calculates global flat and percentage fee', function () {
    Config::set('auction.listing_fee.flat', 2.0);
    Config::set('auction.listing_fee.percent', 0.05); // 5%

    $seller = User::factory()->create(['role' => 'seller', 'wallet_balance' => 10]);
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
        'starting_price' => 100, // 5% of 100 is 5. Total = 7.
    ]);

    $response = $this->actingAs($seller)->getJson(route('seller.auctions.listing-fee-preview', $auction));

    $response->assertOk()
        ->assertJson(['listing_fee' => 7.0]);
});

test('calculates category specific tier fee', function () {
    $category = Category::create(['name' => 'Cars', 'slug' => 'cars']);
    
    ListingFeeTier::create([
        'name' => 'Cars Flat Fee',
        'category_id' => $category->id,
        'fee_amount' => 50.00,
        'fee_percent' => 0,
        'is_active' => true,
    ]);

    $seller = User::factory()->create(['role' => 'seller', 'wallet_balance' => 100]);
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
        'starting_price' => 1000,
    ]);
    
    $auction->categories()->attach($category->id, ['is_primary' => true]);

    $response = $this->actingAs($seller)->getJson(route('seller.auctions.listing-fee-preview', $auction));

    $response->assertOk()
        ->assertJson(['listing_fee' => 50.0]);
});

test('publish charges wallet and marks fee as paid', function () {
    Config::set('auction.listing_fee.flat', 5.0);

    $seller = User::factory()->create(['role' => 'seller', 'wallet_balance' => 10.0]);
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
        'title' => 'Test Item',
        'description' => 'Test Desc',
        'starting_price' => 10,
        'end_time' => now()->addDays(3),
    ]);
    $auction->addMediaFromString('test')->usingFileName('test.jpg')->toMediaCollection('images');

    $response = $this->actingAs($seller)->post(route('seller.auctions.publish', $auction));

    $response->assertRedirect(route('auctions.show', $auction));

    $auction->refresh();
    $seller->refresh();

    expect($auction->status)->toBe(Auction::STATUS_ACTIVE);
    expect((float) $auction->listing_fee_charged)->toBe(5.0);
    expect($auction->listing_fee_paid)->toBeTrue();
    
    // Wallet should be deducted 5.0
    expect((float) $seller->wallet_balance)->toBe(5.0);
});

test('publish fails gracefully with insufficient balance', function () {
    Config::set('auction.listing_fee.flat', 15.0);

    $seller = User::factory()->create(['role' => 'seller', 'wallet_balance' => 10.0]); // Less than 15
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
        'title' => 'Test Item',
        'description' => 'Test Desc',
        'starting_price' => 10,
        'end_time' => now()->addDays(3),
    ]);
    $auction->addMediaFromString('test')->usingFileName('test.jpg')->toMediaCollection('images');

    $response = $this->actingAs($seller)->post(route('seller.auctions.publish', $auction));

    $response->assertRedirect()
        ->assertSessionHas('error'); // Caught the domain exception

    $auction->refresh();
    $seller->refresh();

    expect($auction->status)->toBe(Auction::STATUS_DRAFT); // Did not publish
    expect($auction->listing_fee_paid)->toBeFalse();
    expect((float) $seller->wallet_balance)->toBe(10.0); // Did not deduct
});
