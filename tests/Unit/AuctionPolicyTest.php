<?php

use App\Models\Auction;
use App\Models\User;
use App\Policies\AuctionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows verified seller to create auction', function () {
    $policy = new AuctionPolicy();
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);

    expect($policy->create($seller))->toBeTrue();
});

it('allows deleting draft owned auction only', function () {
    $policy = new AuctionPolicy();
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);

    $draft = Auction::factory()->draft()->create(['user_id' => $seller->id]);
    $active = Auction::factory()->create(['user_id' => $seller->id, 'status' => Auction::STATUS_ACTIVE]);

    expect($policy->delete($seller, $draft))->toBeTrue();
    expect($policy->delete($seller, $active))->toBeFalse();
});
