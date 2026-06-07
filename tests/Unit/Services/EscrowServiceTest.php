<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\EscrowHold;
use App\Models\User;
use App\Services\EscrowService;

test('hold for bid creates and updates escrow hold incrementally', function () {
    $user = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 0]);
    $auction = Auction::factory()->create();
    $service = app(EscrowService::class);

    $service->holdForBid($user, $auction, 100);
    $service->holdForBid($user, $auction, 140);

    $hold = EscrowHold::where('user_id', $user->id)
        ->where('auction_id', $auction->id)
        ->first();

    expect($hold)->not->toBeNull();
    expect($hold->amount)->toBe('140.00');
    expect($user->fresh()->held_balance)->toBe('140.00');
});

test('release for user marks hold released', function () {
    $user = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 100]);
    $auction = Auction::factory()->create();

    EscrowHold::create([
        'user_id' => $user->id,
        'auction_id' => $auction->id,
        'amount' => 100,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);

    app(EscrowService::class)->releaseForUser($user, $auction);

    $hold = EscrowHold::where('user_id', $user->id)
        ->where('auction_id', $auction->id)
        ->first();

    expect($hold->status)->toBe(EscrowHold::STATUS_RELEASED);
    expect($user->fresh()->held_balance)->toBe('0.00');
});

test('release for user allows multiple released hold history rows', function () {
    $user = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 120]);
    $auction = Auction::factory()->create();

    EscrowHold::create([
        'user_id' => $user->id,
        'auction_id' => $auction->id,
        'amount' => 100,
        'status' => EscrowHold::STATUS_RELEASED,
        'released_at' => now()->subMinute(),
    ]);
    EscrowHold::create([
        'user_id' => $user->id,
        'auction_id' => $auction->id,
        'amount' => 120,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);

    app(EscrowService::class)->releaseForUser($user, $auction);

    expect(EscrowHold::where('user_id', $user->id)
        ->where('auction_id', $auction->id)
        ->where('status', EscrowHold::STATUS_RELEASED)
        ->count())->toBe(2);
    expect($user->fresh()->held_balance)->toBe('0.00');
});

test('release outbid holds keeps only current highest bidder held', function () {
    $seller = User::factory()->create();
    $leader = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 150]);
    $outbid = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 140]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'current_price' => 150,
    ]);

    Bid::create([
        'auction_id' => $auction->id,
        'user_id' => $outbid->id,
        'amount' => 140,
        'bid_type' => Bid::TYPE_AUTO,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'AutoBid/System',
        'is_snipe_bid' => false,
    ]);
    Bid::create([
        'auction_id' => $auction->id,
        'user_id' => $leader->id,
        'amount' => 150,
        'bid_type' => Bid::TYPE_AUTO,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'AutoBid/System',
        'is_snipe_bid' => false,
    ]);

    EscrowHold::create([
        'user_id' => $leader->id,
        'auction_id' => $auction->id,
        'amount' => 150,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);
    EscrowHold::create([
        'user_id' => $outbid->id,
        'auction_id' => $auction->id,
        'amount' => 140,
        'status' => EscrowHold::STATUS_ACTIVE,
    ]);

    app(EscrowService::class)->releaseOutbidHolds($auction);

    expect($leader->fresh()->held_balance)->toBe('150.00');
    expect($outbid->fresh()->held_balance)->toBe('0.00');
    expect(EscrowHold::where('auction_id', $auction->id)
        ->where('status', EscrowHold::STATUS_ACTIVE)
        ->pluck('user_id')
        ->all())->toBe([$leader->id]);
});
