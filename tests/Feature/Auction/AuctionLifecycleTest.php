<?php

use App\Models\Auction;
use App\Models\User;
use App\Models\Category;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('seller can create and publish auction', function () {
    $seller = createSeller();
    $category = Category::create([
        'name' => 'Watches',
        'slug' => 'watches',
        'is_active' => true,
    ]);

    $response = $this->actingAs($seller)->post(route('seller.auctions.store'), [
        'title' => 'Vintage Watch',
        'description' => 'A beautiful vintage watch.',
        'starting_price' => 100,
        'min_bid_increment' => 10,
        'end_time' => now()->addDays(7)->toDateTimeString(),
        'category_ids' => [$category->id],
        'condition' => 'used_good',
    ]);

    $auction = Auction::where('title', 'Vintage Watch')->first();
    expect($auction)->not->toBeNull();
    $response->assertRedirect(route('seller.auctions.show', $auction));

    // Initially draft
    expect($auction->status)->toBe(Auction::STATUS_DRAFT);

    // Publish
    $response = $this->actingAs($seller)->post(route('seller.auctions.publish', $auction));
    $response->assertRedirect();
    expect($auction->fresh()->status)->toBe(Auction::STATUS_ACTIVE);
});

test('auction can be closed when time expires', function () {
    $seller = createSeller();
    $auction = createActiveAuction($seller, ['end_time' => now()->subMinute()]);

    expect($auction->status)->toBe(Auction::STATUS_ACTIVE);

    // Run the job or artisan command to close expired auctions
    \App\Jobs\CloseExpiredAuctions::dispatchSync();

    expect($auction->fresh()->status)->toBe(Auction::STATUS_COMPLETED);
});
