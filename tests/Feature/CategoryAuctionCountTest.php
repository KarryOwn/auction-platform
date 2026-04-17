<?php

use App\Models\Auction;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Cache;

test('root category auction counts include descendant categories', function () {
    Cache::flush();

    $electronics = Category::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
    ]);

    $phones = Category::create([
        'parent_id' => $electronics->id,
        'name' => 'Smartphones',
        'slug' => 'smartphones',
        'is_active' => true,
    ]);

    $android = Category::create([
        'parent_id' => $phones->id,
        'name' => 'Android Phones',
        'slug' => 'android-phones',
        'is_active' => true,
    ]);

    $fashion = Category::create([
        'name' => 'Fashion',
        'slug' => 'fashion',
        'is_active' => true,
    ]);

    $liveAuction = Auction::factory()->create([
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
    ]);
    $liveAuction->categories()->sync([$android->id => ['is_primary' => true]]);

    $expiredAuction = Auction::factory()->create([
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->subMinute(),
    ]);
    $expiredAuction->categories()->sync([$android->id => ['is_primary' => true]]);

    $draftAuction = Auction::factory()->create([
        'status' => Auction::STATUS_DRAFT,
        'end_time' => now()->addHour(),
    ]);
    $draftAuction->categories()->sync([$android->id => ['is_primary' => true]]);

    $counts = app(CategoryService::class)->getRootWithAuctionCounts()->keyBy('id');

    expect($counts[$electronics->id]->auctions_count)->toBe(1)
        ->and($counts[$fashion->id]->auctions_count)->toBe(0);
});

test('subcategory auction counts include deeper descendants', function () {
    Cache::flush();

    $vehicles = Category::create([
        'name' => 'Vehicles',
        'slug' => 'vehicles',
        'is_active' => true,
    ]);

    $cars = Category::create([
        'parent_id' => $vehicles->id,
        'name' => 'Cars',
        'slug' => 'cars',
        'is_active' => true,
    ]);

    $electricCars = Category::create([
        'parent_id' => $cars->id,
        'name' => 'Electric Cars',
        'slug' => 'electric-cars',
        'is_active' => true,
    ]);

    $liveAuction = Auction::factory()->create([
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHours(2),
    ]);
    $liveAuction->categories()->sync([$electricCars->id => ['is_primary' => true]]);

    $subcategories = app(CategoryService::class)->getWithAuctionCounts($vehicles->id)->keyBy('id');

    expect($subcategories[$cars->id]->auctions_count)->toBe(1);
});
