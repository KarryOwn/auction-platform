<?php

use App\Models\Auction;
use App\Models\User;
use App\Http\Resources\AuctionResource;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

test('public reserve price is null when disabled', function () {
    $auction = Auction::factory()->make([
        'reserve_price' => 1000.00,
        'reserve_price_visible' => false,
    ]);

    expect($auction->public_reserve_price)->toBeNull();
});

test('public reserve price is formatted when enabled', function () {
    $auction = Auction::factory()->make([
        'reserve_price' => 1000.50,
        'reserve_price_visible' => true,
    ]);

    expect($auction->public_reserve_price)->toBe('1,000.50');
});

test('auction resource exposes reserve price conditionally', function () {
    $seller = User::factory()->make(['id' => 1]);
    $stranger = User::factory()->make(['id' => 2]);
    $admin = User::factory()->make(['id' => 3, 'role' => 'admin']);

    $auction = Auction::factory()->make([
        'user_id' => $seller->id,
        'reserve_price' => 500,
        'reserve_price_visible' => false,
    ]);

    // Stranger
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $stranger);
    $data = (new AuctionResource($auction))->toArray($request);
    expect($data['reserve_price'])->toBeNull();

    // Seller
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $seller);
    $data = (new AuctionResource($auction))->toArray($request);
    expect($data['reserve_price'])->toBe(500.0);

    // Admin
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $admin);
    $data = (new AuctionResource($auction))->toArray($request);
    expect($data['reserve_price'])->toBe(500.0);

    // Visible to everyone
    $auction->reserve_price_visible = true;
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $stranger);
    $data = (new AuctionResource($auction))->toArray($request);
    expect($data['reserve_price'])->toBe(500.0);
});
