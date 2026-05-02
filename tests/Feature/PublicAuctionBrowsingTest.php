<?php

use App\Models\Auction;

test('guests can browse active auctions', function () {
    useSqlBiddingEngine();

    $seller = createSeller();
    $auction = createActiveAuction($seller, [
        'title' => 'Guest Browse Watch',
    ]);

    $this->get(route('auctions.index'))
        ->assertOk()
        ->assertSee($auction->title);
});

test('guests can view active auction details without buyer action forms', function () {
    useSqlBiddingEngine();

    $seller = createSeller();
    $auction = createActiveAuction($seller, [
        'title' => 'Guest Detail Watch',
        'description' => 'Public detail description for guests.',
    ]);

    $this->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertSee($auction->title)
        ->assertSee('Public detail description for guests.')
        ->assertSee('Sign in to bid on this auction.')
        ->assertDontSee('id="bid-form"', false);
});

test('guests can fetch auction live state for public detail polling', function () {
    useSqlBiddingEngine();

    $seller = createSeller();
    $auction = createActiveAuction($seller);

    $this->getJson(route('auctions.live-state', $auction))
        ->assertOk()
        ->assertJsonPath('auction_id', $auction->id);
});

test('guests cannot view draft auction details', function () {
    useSqlBiddingEngine();

    $seller = createSeller();
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
    ]);

    $this->get(route('auctions.show', $auction))
        ->assertNotFound();
});
