<?php

use App\Models\Auction;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('seller can clone own auction into a draft', function () {
    $seller = createSeller();
    $source = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_COMPLETED,
        'start_time' => now()->subDays(2),
        'end_time' => now()->subDay(),
    ]);

    $response = $this->actingAs($seller)
        ->post(route('seller.auctions.clone', $source));

    $clone = Auction::query()->where('cloned_from_auction_id', $source->id)->latest('id')->first();

    $response->assertRedirect(route('seller.auctions.edit', $clone));

    expect($clone)->not->toBeNull();
    expect($clone->status)->toBe(Auction::STATUS_DRAFT)
        ->and($clone->user_id)->toBe($seller->id)
        ->and($clone->cloned_from_auction_id)->toBe($source->id);
});
