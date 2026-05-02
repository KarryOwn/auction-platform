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

test('admin navigation only exposes admin surfaces', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Monitor')
        ->assertSee('Operations')
        ->assertSee('Approvals')
        ->assertSee('Support')
        ->assertSee('System')
        ->assertSee('Catalog')
        ->assertSee('Audit Logs')
        ->assertSee('Seller Approvals')
        ->assertSee('Certificate Approvals')
        ->assertSee('Maintenance Windows')
        ->assertSee('Categories')
        ->assertSee('Brands')
        ->assertSee('Tags')
        ->assertSee('Attributes')
        ->assertSee('Webhook Deliveries')
        ->assertSee('Payments')
        ->assertDontSee('Display Currency')
        ->assertDontSee('Profile')
        ->assertDontSee('Browse Auctions')
        ->assertDontSee('Become a Seller')
        ->assertDontSee('My Bids')
        ->assertDontSee('Won Auctions')
        ->assertDontSee('Watchlist')
        ->assertDontSee('Saved Searches')
        ->assertDontSee('Keyword Alerts')
        ->assertDontSee('Wallet')
        ->assertDontSee('Credits & Power-Ups');
});

test('buyer navigation exposes buyer account feature surfaces', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('Browse Auctions')
        ->assertSee('My Bids')
        ->assertSee('Won Auctions')
        ->assertSee('Watchlist')
        ->assertSee('Saved Searches')
        ->assertSee('Keyword Alerts')
        ->assertSee('Activity')
        ->assertSee('Wallet')
        ->assertSee('Credits & Power-Ups')
        ->assertSee('Referrals')
        ->assertSee('Invoices')
        ->assertSee('Notification Settings')
        ->assertSee('API Tokens')
        ->assertSee('Webhooks')
        ->assertDontSee('Admin Portal')
        ->assertDontSee('Seller Portal');
});

test('seller navigation exposes seller operations', function () {
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);

    $response = $this->actingAs($seller)->get(route('seller.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Seller Portal')
        ->assertSee('Seller Tools')
        ->assertSee('Listings')
        ->assertSee('Performance')
        ->assertSee('Finance')
        ->assertSee('Store')
        ->assertSee('Seller Dashboard')
        ->assertSee('My Auctions')
        ->assertSee('Auction Schedule')
        ->assertSee('Import Listings')
        ->assertSee('Create Auction')
        ->assertSee('Messages')
        ->assertSee('Analytics')
        ->assertSee('Seller Leaderboard')
        ->assertSee('Buyer Questions')
        ->assertSee('Revenue')
        ->assertSee('Tax Documents')
        ->assertSee('Storefront Settings')
        ->assertDontSee('Admin Portal')
        ->assertDontSee('Become a Seller');
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
