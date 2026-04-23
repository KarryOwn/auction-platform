<?php

use App\Models\AnalyticsSellerSnapshot;

test('public seller leaderboard ranks verified sellers and links storefronts', function () {
    $topSeller = createSeller([
        'name' => 'Top Seller',
        'seller_slug' => 'top-seller',
        'seller_bio' => 'Premium collectibles every week.',
    ]);
    $secondSeller = createSeller([
        'name' => 'Second Seller',
        'seller_slug' => 'second-seller',
    ]);
    $unverified = \App\Models\User::factory()->create([
        'role' => \App\Models\User::ROLE_SELLER,
        'name' => 'Hidden Seller',
        'seller_slug' => 'hidden-seller',
        'seller_application_status' => 'pending',
    ]);

    AnalyticsSellerSnapshot::create([
        'user_id' => $secondSeller->id,
        'report_date' => now()->subDay()->toDateString(),
        'active_listings' => 2,
        'completed_sales' => 1,
        'gross_revenue' => 100,
        'avg_sale_price' => 100,
        'avg_rating' => 4,
        'total_bids_received' => 5,
    ]);
    AnalyticsSellerSnapshot::create([
        'user_id' => $topSeller->id,
        'report_date' => now()->subDay()->toDateString(),
        'active_listings' => 4,
        'completed_sales' => 3,
        'gross_revenue' => 900,
        'avg_sale_price' => 300,
        'avg_rating' => 4.9,
        'total_bids_received' => 25,
    ]);
    AnalyticsSellerSnapshot::create([
        'user_id' => $unverified->id,
        'report_date' => now()->subDay()->toDateString(),
        'active_listings' => 10,
        'completed_sales' => 10,
        'gross_revenue' => 5000,
        'avg_sale_price' => 500,
        'avg_rating' => 5,
        'total_bids_received' => 100,
    ]);

    $response = $this->get(route('sellers.leaderboard'));

    $response->assertOk()
        ->assertSeeText('Seller Leaderboard')
        ->assertSeeText('Top Seller')
        ->assertSeeText('Second Seller')
        ->assertSee(route('storefront.show', 'top-seller'), false)
        ->assertDontSeeText('Hidden Seller');

    expect($response->getContent())->toContain('#1');
});

test('homepage links to public seller leaderboard', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Top Sellers')
        ->assertSee(route('sellers.leaderboard'), false);
});
