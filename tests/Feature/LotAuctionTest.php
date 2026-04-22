<?php

use App\Models\Auction;
use App\Models\AuctionLotItem;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    useSqlBiddingEngine();
    Storage::fake('public');
});

test('seller can save a draft lot auction with included items', function () {
    $seller = createSeller();
    $category = Category::create([
        'name' => 'Bundles',
        'slug' => 'bundles',
        'is_active' => true,
    ]);

    $response = $this->actingAs($seller)->post(route('seller.auctions.store'), [
        'title' => 'Camera bundle',
        'description' => 'Camera bundle with lenses and charger for travelling creators.',
        'starting_price' => 250,
        'min_bid_increment' => 10,
        'end_time' => now()->addDays(7)->toDateTimeString(),
        'categories' => [$category->id],
        'primary_category_id' => $category->id,
        'condition' => 'used_good',
        'is_lot' => '1',
        'lot_items' => [
            [
                'name' => 'Camera body',
                'quantity' => 1,
                'condition' => 'Used - Good',
                'description' => 'Main body with original battery.',
                'image' => UploadedFile::fake()->image('body.jpg'),
            ],
            [
                'name' => 'Prime lens',
                'quantity' => 2,
                'condition' => 'Used - Good',
                'description' => 'Two lenses included in the bundle.',
            ],
        ],
    ]);

    $auction = Auction::query()->where('title', 'Camera bundle')->first();

    $response->assertRedirect(route('seller.auctions.edit', $auction));
    expect($auction)->not->toBeNull();
    expect($auction->is_lot)->toBeTrue();
    expect($auction->lotItems()->count())->toBe(2);
    expect($auction->lotItems()->first()->getFirstMediaUrl('image'))->not->toBe('');
});

test('lot auction cannot be published without at least one lot item', function () {
    $seller = createSeller();
    $auction = Auction::factory()->draft()->withImages(1)->create([
        'user_id' => $seller->id,
        'title' => 'Empty bundle',
        'description' => 'Bundle draft with no configured lot items yet.',
        'starting_price' => 100,
        'current_price' => 100,
        'end_time' => now()->addDays(7),
        'condition' => 'used_good',
        'is_lot' => true,
    ]);

    $response = $this->actingAs($seller)->post(route('seller.auctions.publish', $auction));

    $response->assertSessionHasErrors('auction');
    expect($auction->fresh()->status)->toBe(Auction::STATUS_DRAFT);
});

test('auction detail shows whats included section for lot auctions', function () {
    $seller = createSeller();
    $buyer = \App\Models\User::factory()->create();
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Studio starter lot',
        'description' => 'Starter lot for a studio setup with accessories included.',
        'condition' => 'used_good',
        'is_lot' => true,
    ]);

    AuctionLotItem::create([
        'auction_id' => $auction->id,
        'name' => 'Microphone',
        'quantity' => 1,
        'condition' => 'Like New',
        'description' => 'USB microphone included.',
        'sort_order' => 0,
    ]);

    $this->actingAs($buyer)
        ->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertSee("What's Included", false)
        ->assertSeeText('Microphone')
        ->assertSeeText('USB microphone included.');
});
