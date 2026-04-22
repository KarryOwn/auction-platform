<?php

use App\Contracts\BiddingStrategy;
use App\Models\Attribute;
use App\Models\Auction;
use App\Models\AuctionAttributeValue;
use App\Models\Bid;
use App\Models\User;

beforeEach(function () {
    app()->instance(BiddingStrategy::class, new class implements BiddingStrategy {
        public function placeBid(Auction $auction, User $user, float $amount, array $meta = []): Bid
        {
            throw new BadMethodCallException('Not required for comparison tests.');
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

test('comparison endpoint aligns sparse attributes across active auctions', function () {
    $viewer = User::factory()->create();
    $seller = User::factory()->create();

    $material = Attribute::create([
        'name' => 'Material',
        'slug' => 'material',
        'type' => Attribute::TYPE_TEXT,
    ]);

    $size = Attribute::create([
        'name' => 'Size',
        'slug' => 'size',
        'type' => Attribute::TYPE_TEXT,
    ]);

    $watch = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Pilot Watch',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'current_price' => 450,
        'min_bid_increment' => 10,
        'condition' => 'used_good',
    ]);

    $ring = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Diamond Ring',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'current_price' => 900,
        'min_bid_increment' => 25,
        'condition' => 'like_new',
    ]);

    AuctionAttributeValue::create([
        'auction_id' => $watch->id,
        'attribute_id' => $material->id,
        'value' => 'Steel',
    ]);

    AuctionAttributeValue::create([
        'auction_id' => $watch->id,
        'attribute_id' => $size->id,
        'value' => '42mm',
    ]);

    AuctionAttributeValue::create([
        'auction_id' => $ring->id,
        'attribute_id' => $material->id,
        'value' => 'Gold',
    ]);

    $response = $this->actingAs($viewer)->postJson(route('auctions.compare'), [
        'ids' => [$watch->id, $ring->id],
    ]);

    $response->assertOk();

    $payload = $response->json();

    expect(collect($payload['attribute_columns'])->pluck('slug')->all())
        ->toBe(['material', 'size']);

    expect($payload['auctions'])->toHaveCount(2);
    expect($payload['auctions'][0]['id'])->toBe($watch->id);
    expect((float) $payload['auctions'][0]['current_price'])->toBe(450.0);
    expect((float) $payload['auctions'][0]['next_minimum'])->toBe(460.0);
    expect($payload['auctions'][0]['attributes']['material'])->toBe('Steel');
    expect($payload['auctions'][0]['attributes']['size'])->toBe('42mm');
    expect($payload['auctions'][1]['attributes']['material'])->toBe('Gold');
    expect($payload['auctions'][1]['attributes']['size'])->toBeNull();
});

test('comparison endpoint excludes inactive auctions from the response', function () {
    $viewer = User::factory()->create();
    $seller = User::factory()->create();

    $activeAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
    ]);

    $draftAuction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
    ]);

    $response = $this->actingAs($viewer)->postJson(route('auctions.compare'), [
        'ids' => [$activeAuction->id, $draftAuction->id],
    ]);

    $response->assertOk();

    expect($response->json('auctions'))->toHaveCount(1);
    expect($response->json('auctions.0.id'))->toBe($activeAuction->id);
});
