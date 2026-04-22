<?php

use App\Models\Auction;
use App\Models\User;
use App\Models\AutoBid;
use App\Contracts\BiddingStrategy;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('auto bid increases when outbid', function () {
    $seller = createSeller();
    $bidder1 = User::factory()->create(['wallet_balance' => 1000]);
    $bidder2 = User::factory()->create(['wallet_balance' => 1000]);
    $auction = createActiveAuction($seller, ['starting_price' => 100, 'current_price' => 100, 'min_bid_increment' => 5]);

    // Bidder 1 sets up auto-bid
    AutoBid::create([
        'user_id' => $bidder1->id,
        'auction_id' => $auction->id,
        'max_amount' => 200,
        'is_active' => true,
    ]);

    // Bidder 2 places manual bid
    $this->actingAs($bidder2)->postJson(route('auctions.bid', $auction), ['amount' => 110]);

    // Since it's sync queue in tests (if configured) or we manually run job
    // Actually the PlaceBid listener dispatches ProcessAutoBids job
    \App\Jobs\ProcessAutoBids::dispatchSync($auction->id, $bidder2->id);

    $auction->refresh();
    // Bidder 1 should have auto-bid to 115 (110 + increment)
    expect((float) $auction->current_price)->toBe(115.0);
    expect($auction->bids()->latest()->first()->user_id)->toBe($bidder1->id);
});
