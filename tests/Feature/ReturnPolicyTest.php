<?php

use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\Config;

test('seller defaults to no returns', function () {
    $seller = User::factory()->create();
    expect($seller->return_policy_type)->toBe('no_returns');
    expect($seller->return_policy_label)->toBe('No returns accepted');
});

test('seller custom return policy formats label correctly', function () {
    $seller = User::factory()->create([
        'return_policy_type' => 'custom',
        'return_policy_custom' => 'I only accept returns for broken items.',
    ]);
    expect($seller->return_policy_label)->toBe('I only accept returns for broken items.');
});

test('auction falls back to seller policy if no override', function () {
    $seller = User::factory()->create([
        'return_policy_type' => 'returns_accepted',
        'return_window_days' => 14,
    ]);
    
    $auction = Auction::factory()->create(['user_id' => $seller->id]);
    
    expect($auction->effective_return_policy)->toBe('Returns accepted within 14 days');
});

test('auction override takes precedence over seller policy', function () {
    $seller = User::factory()->create([
        'return_policy_type' => 'returns_accepted',
        'return_window_days' => 14,
    ]);
    
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'return_policy_override' => 'no_returns',
    ]);
    
    expect($auction->effective_return_policy)->toBe('No returns accepted');
});

test('publishing snapshots the effective return policy', function () {
    Config::set('auction.listing_fee.flat', 0.0);
    Config::set('auction.listing_fee.percent', 0.0);

    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
        'return_policy_type' => 'no_returns',
    ]);

    $auction = Auction::factory()->draft()->withImages(1)->create([
        'user_id' => $seller->id,
        'return_policy_override' => 'custom',
        'return_policy_custom_override' => 'Snapshotted policy!',
    ]);

    $this->actingAs($seller)
        ->post(route('seller.auctions.publish', $auction))
        ->assertRedirect(route('auctions.show', $auction));

    $auction->refresh();
    
    // Change the override after publish
    $auction->update(['return_policy_override' => 'no_returns']);
    
    // The accessor should prioritize the snapshot
    expect($auction->effective_return_policy_snapshot)->toBe('Snapshotted policy!');
    expect($auction->effective_return_policy)->toBe('Snapshotted policy!');
});
