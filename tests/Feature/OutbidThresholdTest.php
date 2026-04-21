<?php

use App\Models\Auction;
use App\Models\AuctionWatcher;
use App\Models\Bid;
use App\Models\User;
use App\Events\BidPlaced;
use App\Listeners\HandleBidPlaced;
use App\Notifications\OutbidNotification;
use App\Notifications\PriceAlertNotification;
use App\Services\EscrowService;
use Illuminate\Support\Facades\Notification;

test('outbid notification respects watcher threshold', function () {
    Notification::fake();

    $seller = User::factory()->create();
    $buyer1 = User::factory()->create(); // Previous highest
    $buyer2 = User::factory()->create(); // New bidder
    
    $auction = Auction::factory()->create(['user_id' => $seller->id]);
    
    $bid1 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer1->id,
        'amount' => 100,
    ]);

    // Buyer 1 says "Only notify me if outbid by $20 or more"
    AuctionWatcher::create([
        'auction_id' => $auction->id,
        'user_id' => $buyer1->id,
        'outbid_threshold_amount' => 20,
    ]);

    // Bid 2 is $110. Increment is $10. Less than $20 threshold.
    $bid2 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer2->id,
        'amount' => 110,
    ]);

    $listener = new HandleBidPlaced(app(EscrowService::class));
    $listener->handle(new BidPlaced($bid2, $auction));

    Notification::assertNotSentTo([$buyer1], OutbidNotification::class);
});

test('outbid notification sends if threshold exceeded', function () {
    Notification::fake();

    $seller = User::factory()->create();
    $buyer1 = User::factory()->create(); 
    $buyer2 = User::factory()->create(); 
    
    $auction = Auction::factory()->create(['user_id' => $seller->id]);
    
    $bid1 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer1->id,
        'amount' => 100,
    ]);

    AuctionWatcher::create([
        'auction_id' => $auction->id,
        'user_id' => $buyer1->id,
        'outbid_threshold_amount' => 20,
    ]);

    // Bid 2 is $150. Increment is $50. More than $20 threshold.
    $bid2 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer2->id,
        'amount' => 150,
    ]);

    $listener = new HandleBidPlaced(app(EscrowService::class));
    $listener->handle(new BidPlaced($bid2, $auction));

    Notification::assertSentTo([$buyer1], OutbidNotification::class);
});

test('price alert notification fires exactly once', function () {
    Notification::fake();

    $seller = User::factory()->create();
    $watcherUser = User::factory()->create();
    $bidder = User::factory()->create();
    
    $auction = Auction::factory()->create(['user_id' => $seller->id]);

    $watcher = AuctionWatcher::create([
        'auction_id' => $auction->id,
        'user_id' => $watcherUser->id,
        'price_alert_at' => 500,
    ]);

    $bid = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $bidder->id,
        'amount' => 600, // Exceeds alert threshold
    ]);

    $listener = new HandleBidPlaced(app(EscrowService::class));
    $listener->handle(new BidPlaced($bid, $auction));

    Notification::assertSentTo([$watcherUser], PriceAlertNotification::class);

    $watcher->refresh();
    expect($watcher->price_alert_sent)->toBeTrue();

    // 2nd bid should not trigger the alert again
    Notification::fake();
    
    $bid2 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $bidder->id,
        'amount' => 700,
    ]);

    $listener->handle(new BidPlaced($bid2, $auction));

    Notification::assertNotSentTo([$watcherUser], PriceAlertNotification::class);
});

test('user can update watch preferences', function () {
    $user = User::factory()->create();
    $auction = Auction::factory()->create();

    // Initial Watch
    $this->actingAs($user)->postJson(route('auctions.watch', $auction));

    // Update preferences (doesn't delete)
    $response = $this->actingAs($user)->postJson(route('auctions.watch', $auction), [
        'outbid_threshold' => 50.00,
        'price_alert_at' => 1000.00,
    ]);

    $response->assertOk()->assertJson(['watching' => true]);

    $watcher = AuctionWatcher::where('user_id', $user->id)->first();
    expect((float) $watcher->outbid_threshold_amount)->toBe(50.0);
    expect((float) $watcher->price_alert_at)->toBe(1000.0);
});
