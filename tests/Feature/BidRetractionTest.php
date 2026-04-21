<?php

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\BidRetractionRequest;
use App\Models\User;
use App\Services\EscrowService;
use Mockery\MockInterface;

test('user can submit bid retraction request', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
    ]);

    $bid = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer->id,
        'amount' => 100,
        'is_retracted' => false,
    ]);

    $response = $this->actingAs($buyer)->postJson(route('bids.retract', $bid), [
        'reason' => 'I made a typo.',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Bid retraction request submitted.']);

    $request = BidRetractionRequest::where('bid_id', $bid->id)->first();
    expect($request)->not->toBeNull();
    expect($request->reason)->toBe('I made a typo.');
    expect($request->status)->toBe('pending');
});

test('cannot retract bid if outbid', function () {
    $seller = User::factory()->create();
    $buyer1 = User::factory()->create();
    $buyer2 = User::factory()->create();
    
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
    ]);

    $bid1 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer1->id,
        'amount' => 100,
    ]);

    // buyer2 outbids buyer1
    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer2->id,
        'amount' => 200,
    ]);

    $response = $this->actingAs($buyer1)->postJson(route('bids.retract', $bid1), [
        'reason' => 'I made a typo.',
    ]);

    $response->assertStatus(422)
        ->assertJson(['error' => 'You can only request retraction for your current highest bid.']);
});

test('admin can approve retraction request and revert price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $seller = User::factory()->create();
    $buyer1 = User::factory()->create(['wallet_balance' => 100, 'held_balance' => 100]); // $100 held
    $buyer2 = User::factory()->create();
    
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'starting_price' => 50,
        'current_price' => 200,
    ]);

    $bid1 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer2->id,
        'amount' => 150, // Previous valid bid
    ]);

    $bid2 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer1->id,
        'amount' => 200, // To be retracted
    ]);

    $retractionRequest = BidRetractionRequest::create([
        'bid_id' => $bid2->id,
        'user_id' => $buyer1->id,
        'auction_id' => $auction->id,
        'reason' => 'Mistake',
    ]);

    // Mock EscrowService
    $escrowService = Mockery::mock(EscrowService::class, function (MockInterface $mock) use ($buyer1, $auction) {
        $mock->shouldReceive('releaseForUser')->once();
    });
    app()->instance(EscrowService::class, $escrowService);

    // Mock BiddingStrategy to check `initializePrice`
    $biddingStrategy = Mockery::mock(BiddingStrategy::class, function (MockInterface $mock) {
        $mock->shouldReceive('initializePrice')->once();
    });
    app()->instance(BiddingStrategy::class, $biddingStrategy);

    $response = $this->actingAs($admin)->postJson(route('admin.bid-retractions.approve', $retractionRequest), [
        'notes' => 'Approved mistake',
    ]);

    $response->assertOk();

    $retractionRequest->refresh();
    $auction->refresh();
    $bid2->refresh();

    expect($retractionRequest->status)->toBe('approved');
    expect($retractionRequest->reviewed_by)->toBe($admin->id);
    expect($bid2->is_retracted)->toBeTrue();
    // Price reverted to previous valid bid amount
    expect((float) $auction->current_price)->toBe(150.0);
});

test('admin can decline retraction request', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $buyer = User::factory()->create();
    
    $auction = Auction::factory()->create(['status' => Auction::STATUS_ACTIVE]);

    $bid = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer->id,
        'amount' => 200,
    ]);

    $retractionRequest = BidRetractionRequest::create([
        'bid_id' => $bid->id,
        'user_id' => $buyer->id,
        'auction_id' => $auction->id,
        'reason' => 'Mistake',
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.bid-retractions.decline', $retractionRequest), [
        'notes' => 'No mistakes allowed',
    ]);

    $response->assertOk();

    $retractionRequest->refresh();

    expect($retractionRequest->status)->toBe('declined');
    expect($bid->fresh()->is_retracted)->toBeFalse(); // Not retracted
});
