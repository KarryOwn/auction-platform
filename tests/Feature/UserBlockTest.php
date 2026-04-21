<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Events\BidPlaced;
use App\Listeners\HandleBidPlaced;
use App\Notifications\OutbidNotification;
use App\Services\EscrowService;
use Illuminate\Support\Facades\Notification;

test('user can block and unblock another user', function () {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();

    $response = $this->actingAs($blocker)->postJson(route('users.block', $blocked));
    $response->assertOk()->assertJson(['blocked' => true]);

    expect($blocker->hasBlocked($blocked->id))->toBeTrue();
    expect($blocked->isBlockedBy($blocker->id))->toBeTrue();

    $response = $this->actingAs($blocker)->postJson(route('users.block', $blocked));
    $response->assertOk()->assertJson(['blocked' => false]);

    expect($blocker->hasBlocked($blocked->id))->toBeFalse();
});

test('cannot block yourself', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('users.block', $user));
    $response->assertStatus(422)->assertJson(['error' => 'Cannot block yourself.']);
});

test('blocked user cannot be messaged', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    
    $buyer->blockedUsers()->attach($seller->id);

    $auction = Auction::factory()->create(['user_id' => $seller->id]);

    $response = $this->actingAs($buyer)->post(route('conversations.start', $auction), [
        'body' => 'Hello there!',
    ]);

    $response->assertSessionHasErrors('message');
});

test('blocked user does not receive outbid notifications', function () {
    Notification::fake();

    $seller = User::factory()->create();
    $buyer1 = User::factory()->create(); // Will be outbid
    $buyer2 = User::factory()->create(); // The outbidder
    
    // Buyer1 blocks Buyer2
    $buyer1->blockedUsers()->attach($buyer2->id);

    $auction = Auction::factory()->create(['user_id' => $seller->id]);
    
    $bid1 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer1->id,
        'amount' => 10,
    ]);

    $bid2 = Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $buyer2->id,
        'amount' => 20,
    ]);

    $listener = new HandleBidPlaced(app(EscrowService::class));
    $listener->handle(new BidPlaced($bid2, $auction));

    Notification::assertNotSentTo([$buyer1], OutbidNotification::class);
});
