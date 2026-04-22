<?php

use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

test('featured scope returns only active non-expired featured categories in order', function () {
    $second = Category::create([
        'name' => 'Collectibles',
        'slug' => 'collectibles',
        'is_active' => true,
        'is_featured' => true,
        'featured_sort_order' => 2,
    ]);

    $first = Category::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
        'is_featured' => true,
        'featured_sort_order' => 1,
        'featured_until' => now()->addDay(),
    ]);

    Category::create([
        'name' => 'Expired',
        'slug' => 'expired',
        'is_active' => true,
        'is_featured' => true,
        'featured_until' => now()->subHour(),
    ]);

    Category::create([
        'name' => 'Inactive',
        'slug' => 'inactive',
        'is_active' => false,
        'is_featured' => true,
    ]);

    $featured = app(CategoryService::class)->getFeaturedCategories();

    expect($featured->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('featured categories cache is invalidated when category service is reset', function () {
    Cache::forget('categories:featured:v1');

    $category = Category::create([
        'name' => 'Vehicles',
        'slug' => 'vehicles',
        'is_active' => true,
        'is_featured' => true,
    ]);

    $service = app(CategoryService::class);
    $service->getFeaturedCategories();

    expect(Cache::has('categories:featured:v1'))->toBeTrue();

    $category->update(['is_featured' => false]);
    $service->invalidateCache();

    expect(Cache::has('categories:featured:v1'))->toBeFalse();
});

test('featured category can expire via scheduled cleanup query', function () {
    $expired = Category::create([
        'name' => 'Seasonal',
        'slug' => 'seasonal',
        'is_active' => true,
        'is_featured' => true,
        'featured_until' => now()->subMinute(),
    ]);

    Category::where('is_featured', true)
        ->whereNotNull('featured_until')
        ->where('featured_until', '<=', now())
        ->update(['is_featured' => false]);

    expect($expired->fresh()->is_featured)->toBeFalse();
    expect($expired->fresh()->is_currently_featured)->toBeFalse();
});
