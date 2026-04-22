<?php

use App\Models\Auction;
use App\Models\User;
use App\Jobs\NotifySellerFollowers;
use App\Notifications\NewSellerListingNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('user can follow and unfollow a verified seller', function () {
    $follower = User::factory()->create();
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);

    // Follow
    $response = $this->actingAs($follower)->postJson(route('sellers.follow', $seller));
    
    $response->assertOk()
        ->assertJson(['following' => true]);

    expect($follower->following()->where('seller_id', $seller->id)->exists())->toBeTrue();
    expect($seller->follower_count)->toBe(1);

    // Unfollow
    $response = $this->actingAs($follower)->postJson(route('sellers.follow', $seller));
    
    $response->assertOk()
        ->assertJson(['following' => false]);

    expect($follower->following()->where('seller_id', $seller->id)->exists())->toBeFalse();
    expect($seller->follower_count)->toBe(0);
});

test('user cannot follow themselves', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);

    $response = $this->actingAs($seller)->postJson(route('sellers.follow', $seller));
    
    $response->assertStatus(422)
        ->assertJson(['error' => 'Cannot follow yourself.']);
});

test('user cannot follow unverified seller', function () {
    $follower = User::factory()->create();
    $seller = User::factory()->create([
        'role' => 'seller', // missing verified status
    ]);

    $response = $this->actingAs($follower)->postJson(route('sellers.follow', $seller));
    
    $response->assertStatus(422)
        ->assertJson(['error' => 'User is not a verified seller.']);
});

test('publishing an auction dispatches follower notification job', function () {
    Queue::fake();

    $seller = User::factory()->create();
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
    ]);

    $auction->update(['status' => Auction::STATUS_ACTIVE]);

    Queue::assertPushed(NotifySellerFollowers::class, function ($job) use ($auction) {
        return $job->auctionId === $auction->id;
    });
});

test('followers receive notification when job runs', function () {
    Notification::fake();

    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);
    $follower = User::factory()->create();
    
    $follower->following()->attach($seller->id, ['notify_new_listings' => true]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
    ]);

    $job = new NotifySellerFollowers($auction->id);
    $job->handle();

    Notification::assertSentTo(
        [$follower], NewSellerListingNotification::class
    );
});

test('storefront shows follow seller control for authenticated buyers', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create([
        'role' => 'seller',
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
        'seller_slug' => 'verified-seller',
    ]);

    $response = $this->actingAs($buyer)->get(route('storefront.show', $seller->seller_slug));

    $response->assertOk()
        ->assertSee('Follow Seller')
        ->assertSee('followers');
});
