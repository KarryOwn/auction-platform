<?php

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    app()->instance(BiddingStrategy::class, new class implements BiddingStrategy {
        public function placeBid(Auction $auction, User $user, float $amount, array $meta = []): Bid
        {
            throw new \BadMethodCallException('Not required for auction search tests.');
        }

        public function getCurrentPrice(Auction $auction): float
        {
            return (float) $auction->current_price;
        }

        public function initializePrice(Auction $auction): void
        {
            // No-op for tests.
        }

        public function cleanup(Auction $auction): void
        {
            // No-op for tests.
        }
    });
});

test('auction index search matches seller name', function () {
    $viewer = User::factory()->create();
    $matchingSeller = User::factory()->create(['name' => 'Alice Seller']);
    $otherSeller = User::factory()->create(['name' => 'Bob Merchant']);

    $matchingAuction = Auction::factory()->create([
        'user_id' => $matchingSeller->id,
        'title' => 'Vintage Camera Bundle',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
    ]);

    $otherAuction = Auction::factory()->create([
        'user_id' => $otherSeller->id,
        'title' => 'Mountain Bike Pro',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('auctions.index', ['q' => 'Alice']));

    $response->assertOk()
        ->assertSee($matchingAuction->title)
        ->assertDontSee($otherAuction->title);
});

test('category search matches seller name', function () {
    $category = Category::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
    ]);

    $matchingSeller = User::factory()->create(['name' => 'Alice Seller']);
    $otherSeller = User::factory()->create(['name' => 'Bob Merchant']);

    $matchingAuction = Auction::factory()->create([
        'user_id' => $matchingSeller->id,
        'title' => 'Retro Game Console',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
    ]);
    $matchingAuction->categories()->sync([$category->id => ['is_primary' => true]]);

    $otherAuction = Auction::factory()->create([
        'user_id' => $otherSeller->id,
        'title' => 'Smart Home Speaker',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
    ]);
    $otherAuction->categories()->sync([$category->id => ['is_primary' => true]]);

    $response = $this->get(route('categories.show', [
        'category' => $category,
        'q' => 'Alice',
    ]));

    $response->assertOk()
        ->assertSee($matchingAuction->title)
        ->assertDontSee($otherAuction->title);
});
