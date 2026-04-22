<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Config;

test('category commission override is used for platform fee calculation', function () {
    Config::set('auction.platform_fee_percent', 0.05);

    $seller = User::factory()->create();
    $category = Category::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'commission_rate' => 0.08,
        'is_active' => true,
    ]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'winning_bid_amount' => 1000,
    ]);

    $auction->categories()->sync([$category->id => ['is_primary' => true]]);

    $paymentService = app(PaymentService::class);

    expect($paymentService->calculatePlatformFee(1000, $auction))->toBe(80.0);
    expect($paymentService->calculateSellerAmount(1000, $auction))->toBe(920.0);
});

test('category commission rate inherits from parent category', function () {
    Config::set('auction.platform_fee_percent', 0.05);

    $parent = Category::create([
        'name' => 'Collectibles',
        'slug' => 'collectibles',
        'commission_rate' => 0.03,
        'is_active' => true,
    ]);

    $child = Category::create([
        'name' => 'Coins',
        'slug' => 'coins',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    expect($child->fresh()->effective_commission_rate)->toBe(0.03);
    expect($child->fresh()->effective_commission_percent)->toBe(3.0);
});

test('category commission falls back to global config and api returns percent view', function () {
    Config::set('auction.platform_fee_percent', 0.05);

    $viewer = User::factory()->create();
    $category = Category::create([
        'name' => 'Vehicles',
        'slug' => 'vehicles',
        'is_active' => true,
    ]);

    expect($category->effective_commission_rate)->toBe(0.05);

    $response = $this->actingAs($viewer)->get(route('api.categories.commission', ['id' => $category->id]));

    $response->assertOk()
        ->assertJson([
            'commission_rate' => 0.05,
            'commission_pct' => 5.0,
        ]);
});
