<?php

use App\Models\Auction;
use App\Models\User;

test('admin login lands on the admin dashboard', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $response = $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

test('admin navigation only exposes admin and profile surfaces', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Admin Monitor')
        ->assertSee('Admin Activity')
        ->assertSee('Seller Approvals')
        ->assertSee('Certificate Approvals')
        ->assertDontSee('Browse Auctions')
        ->assertDontSee('Become a Seller')
        ->assertDontSee('My Bids')
        ->assertDontSee('Won Auctions')
        ->assertDontSee('Watchlist')
        ->assertDontSee('Wallet')
        ->assertDontSee('Credits & Power-Ups');
});

test('admin users are redirected away from buyer and seller application pages', function (string $route) {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $this->actingAs($admin)
        ->get(route($route))
        ->assertRedirect(route('admin.dashboard'));
})->with([
    'dashboard',
    'user.bids',
    'user.won-auctions',
    'user.watchlist',
    'user.wallet',
    'seller.apply.form',
    'seller.application.status',
]);

test('admin users cannot place bids', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'starting_price' => 10,
        'current_price' => 10,
        'min_bid_increment' => 1,
    ]);

    $this->actingAs($admin)
        ->postJson(route('auctions.bid', $auction), ['amount' => 11])
        ->assertForbidden();
});
