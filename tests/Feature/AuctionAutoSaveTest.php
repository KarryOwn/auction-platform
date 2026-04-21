<?php

use App\Models\Auction;
use App\Models\User;

test('seller can auto-save draft auction', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
        'title' => 'Initial Title',
    ]);

    $response = $this->actingAs($seller)->patchJson(route('seller.auctions.auto-save', $auction), [
        'title' => 'Updated Auto-Saved Title',
        'description' => 'A new description for my draft',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['saved', 'auto_saved_at']);

    $auction->refresh();
    expect($auction->title)->toBe('Updated Auto-Saved Title');
    expect($auction->description)->toBe('A new description for my draft');
    expect($auction->auto_saved_at)->not->toBeNull();
});

test('only allowed fields can be auto-saved', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_DRAFT,
    ]);

    $response = $this->actingAs($seller)->patchJson(route('seller.auctions.auto-save', $auction), [
        'title' => 'Valid Change',
        'status' => Auction::STATUS_ACTIVE, // Not allowed
    ]);

    $response->assertOk();

    $auction->refresh();
    expect($auction->title)->toBe('Valid Change');
    expect($auction->status)->toBe(Auction::STATUS_DRAFT); // Did not change
});

test('cannot auto-save active auction', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($seller)->patchJson(route('seller.auctions.auto-save', $auction), [
        'title' => 'Sneaky Edit',
    ]);

    $response->assertStatus(422)
        ->assertJson(['error' => 'Only drafts can be auto-saved.']);

    $auction->refresh();
    expect($auction->title)->not->toBe('Sneaky Edit');
});

test('stranger cannot auto-save auction', function () {
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
        'title' => 'My Draft',
    ]);

    $response = $this->actingAs($stranger)->patchJson(route('seller.auctions.auto-save', $auction), [
        'title' => 'Hacked Draft',
    ]);

    $response->assertForbidden();

    $auction->refresh();
    expect($auction->title)->toBe('My Draft');
});
