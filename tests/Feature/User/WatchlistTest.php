<?php

use App\Models\Auction;
use App\Models\AuctionWatcher;
use App\Models\User;

test('watchlist page loads for authenticated user', function () {
    $user = User::factory()->create();
    $seller = createSeller();

    $activeAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
    ]);

    $endedAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_COMPLETED,
        'end_time' => now()->subHour(),
    ]);

    AuctionWatcher::create(['auction_id' => $activeAuction->id, 'user_id' => $user->id]);
    AuctionWatcher::create(['auction_id' => $endedAuction->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('user.watchlist'))
        ->assertOk();
});
