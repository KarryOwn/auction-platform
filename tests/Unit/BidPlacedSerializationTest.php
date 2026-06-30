<?php

namespace Tests\Unit;

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BidPlacedSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_serialize_and_deserialize_with_unpersisted_bid()
    {
        $auction = Auction::factory()->create();
        
        $bid = new Bid([
            'auction_id' => $auction->id,
            'user_id' => 1,
            'amount' => 100.50,
            'accepted_bid_id' => 'ulid-test',
        ]);

        $event = new BidPlaced($bid, $auction);

        // Serialize and deserialize
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(BidPlaced::class, $unserialized);
        $this->assertInstanceOf(Bid::class, $unserialized->bid);
        $this->assertFalse($unserialized->bid->exists);
        $this->assertEquals(100.50, $unserialized->bid->amount);
        $this->assertEquals('ulid-test', $unserialized->bid->accepted_bid_id);
    }
}
