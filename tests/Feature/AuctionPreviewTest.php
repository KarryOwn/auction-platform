<?php

use App\Models\Auction;
use App\Models\User;

test('seller can preview draft auction', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
        'title' => 'My Awesome Draft',
    ]);

    $response = $this->actingAs($seller)->get(route('seller.auctions.preview', $auction));

    $response->assertOk()
        ->assertSee('My Awesome Draft')
        ->assertSee('Preview Mode')
        ->assertSee('Publish Now');
});

test('previewing an active auction redirects to regular show page', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($seller)->get(route('seller.auctions.preview', $auction));

    $response->assertRedirect(route('auctions.show', $auction));
});

test('stranger cannot preview seller draft', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    $stranger = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
    ]);

    $response = $this->actingAs($stranger)->get(route('seller.auctions.preview', $auction));

    $response->assertForbidden();
});

test('unauthenticated user cannot preview seller draft', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
    ]);

    $response = $this->get(route('seller.auctions.preview', $auction));

    $response->assertRedirect('/login');
});
