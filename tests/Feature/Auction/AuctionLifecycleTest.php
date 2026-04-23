<?php

use App\Models\Auction;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\UploadedFile;

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
        'categories' => [$category->id],
        'primary_category_id' => $category->id,
        'condition' => 'used_good',
    ]);

    $auction = Auction::where('title', 'Vintage Watch')->first();
    expect($auction)->not->toBeNull();
    $response->assertRedirect(route('seller.auctions.edit', $auction));

    // Initially draft
    expect($auction->status)->toBe(Auction::STATUS_DRAFT);

    $auction->addMedia(UploadedFile::fake()->image('vintage-watch.png', 1200, 900))
        ->toMediaCollection('images');

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
