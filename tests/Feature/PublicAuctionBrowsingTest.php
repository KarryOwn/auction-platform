<?php

use App\Models\Auction;
use App\Models\User;

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

test('viewing an expired active auction finalizes the winner before rendering', function () {
    useSqlBiddingEngine();

    $seller = createSeller();
    $bidder = User::factory()->create([
        'name' => 'KarryOwn',
        'wallet_balance' => 2000,
    ]);
    $auction = createActiveAuction($seller, [
        'title' => 'Expired Detail Winner',
        'current_price' => 1200,
        'starting_price' => 1200,
        'min_bid_increment' => 5,
        'end_time' => now()->addMinutes(10),
    ]);

    $this->actingAs($bidder)
        ->postJson(route('auctions.bid', $auction), ['amount' => 1214.88])
        ->assertOk();

    $auction->update(['end_time' => now()->subSecond()]);

    $this->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertSee('Auction has ended')
        ->assertSee('Won by')
        ->assertSee('KarryOwn')
        ->assertDontSee('No winner determined.');

    $auction->refresh();

    expect($auction->status)->toBe(Auction::STATUS_COMPLETED)
        ->and($auction->winner_id)->toBe($bidder->id)
        ->and((float) $auction->winning_bid_amount)->toBe(1214.88);
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
