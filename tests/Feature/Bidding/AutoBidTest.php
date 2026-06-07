<?php

use App\Models\Auction;
use App\Models\User;
use App\Models\AutoBid;
use App\Contracts\BiddingStrategy;
use App\Events\BidPlaced;
use App\Listeners\HandleBidPlaced;
use App\Models\Bid;
use App\Models\EscrowHold;
use App\Services\EscrowService;

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

test('batched auto bid events do not release the final leader escrow', function () {
    $seller = createSeller();
    $bidder1 = User::factory()->create(['wallet_balance' => 5000, 'held_balance' => 1535.68]);
    $bidder2 = User::factory()->create(['wallet_balance' => 5000, 'held_balance' => 1516.69]);
    $auction = createActiveAuction($seller, [
        'starting_price' => 1400,
        'current_price' => 1535.68,
        'min_bid_increment' => 18.99,
    ]);

    EscrowHold::create([
        'user_id' => $bidder1->id,
        'auction_id' => $auction->id,
        'amount' => 1535.68,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);
    EscrowHold::create([
        'user_id' => $bidder2->id,
        'auction_id' => $auction->id,
        'amount' => 1516.69,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);

    $bids = collect([
        [$bidder1, 1421.74, Bid::TYPE_MANUAL],
        [$bidder2, 1440.73, Bid::TYPE_AUTO],
        [$bidder1, 1459.72, Bid::TYPE_AUTO],
        [$bidder2, 1478.71, Bid::TYPE_AUTO],
        [$bidder1, 1497.70, Bid::TYPE_AUTO],
        [$bidder2, 1516.69, Bid::TYPE_AUTO],
        [$bidder1, 1535.68, Bid::TYPE_AUTO],
    ])->map(fn (array $row) => Bid::create([
        'auction_id' => $auction->id,
        'user_id' => $row[0]->id,
        'amount' => $row[1],
        'bid_type' => $row[2],
        'previous_amount' => $row[1] - 18.99,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'AutoBid/System',
        'is_snipe_bid' => false,
    ]));

    $listener = app(HandleBidPlaced::class);

    app(EscrowService::class)->releaseOutbidHolds($auction);

    foreach ($bids as $bid) {
        $listener->handle(new BidPlaced($bid, $auction->fresh()));
    }

    expect(EscrowHold::where('auction_id', $auction->id)
        ->where('user_id', $bidder1->id)
        ->where('status', EscrowHold::STATUS_ACTIVE)
        ->value('amount'))->toBe('1535.68');
    expect(EscrowHold::where('auction_id', $auction->id)
        ->where('user_id', $bidder2->id)
        ->where('status', EscrowHold::STATUS_RELEASED)
        ->value('amount'))->toBe('1516.69');
    expect($bidder1->fresh()->held_balance)->toBe('1535.68');
    expect($bidder2->fresh()->held_balance)->toBe('0.00');
});
