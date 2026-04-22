<?php

use App\Models\Auction;
use App\Models\AuctionWatcher;
use App\Models\User;
use App\Notifications\AuctionEndingSoonNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    useSqlBiddingEngine();
});

test('ending soon command notifies opted-in watchers', function () {
    Notification::fake();

    $seller = createSeller();
    $watcher = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addMinutes(20),
        'ending_soon_notified' => false,
    ]);

    AuctionWatcher::create([
        'auction_id' => $auction->id,
        'user_id' => $watcher->id,
        'notify_ending' => true,
        'notify_outbid' => true,
        'notify_cancelled' => true,
    ]);

    $this->artisan('auctions:notify-ending-soon --minutes=30')
        ->assertExitCode(0);

    Notification::assertSentTo($watcher, AuctionEndingSoonNotification::class);
    expect($auction->fresh()->ending_soon_notified)->toBeTrue();
});
