<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuctionCloneService;

test('seller can clone a completed auction', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    $auction = Auction::factory()->completed()->create([
        'user_id' => $seller->id,
        'title' => 'Original Auction',
        'buy_it_now_price' => 500,
    ]);

    $category = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat']);
    $auction->categories()->sync([$category->id => ['is_primary' => true]]);
    
    $tag = Tag::create(['name' => 'Test Tag', 'slug' => 'test-tag']);
    $auction->tags()->sync([$tag->id]);

    $response = $this->actingAs($seller)->post(route('seller.auctions.clone', $auction));

    $cloned = Auction::where('cloned_from_auction_id', $auction->id)->first();
    
    expect($cloned)->not->toBeNull();
    expect($cloned->title)->toBe('Original Auction');
    expect($cloned->status)->toBe(Auction::STATUS_DRAFT);
    expect($cloned->buy_it_now_enabled)->toBeFalse();
    expect($cloned->categories->first()->id)->toBe($category->id);
    expect($cloned->tags->first()->id)->toBe($tag->id);

    $response->assertRedirect(route('seller.auctions.edit', $cloned));
});

test('cannot clone active auction', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($seller)->post(route('seller.auctions.clone', $auction));

    $response->assertForbidden();
});

test('stranger cannot clone auction', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    $stranger = User::factory()->create(['role' => 'seller']);
    $auction = Auction::factory()->completed()->create([
        'user_id' => $seller->id,
    ]);

    $response = $this->actingAs($stranger)->post(route('seller.auctions.clone', $auction));

    $response->assertForbidden();
});
