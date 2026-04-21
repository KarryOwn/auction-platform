<?php

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use App\Events\AuctionClosed;
use App\Services\EscrowService;
use App\Services\PaymentService;
use App\Models\Invoice;
use Mockery\MockInterface;

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

test('can purchase auction via buy it now', function () {
    Event::fake([AuctionClosed::class]);

    $seller = User::factory()->create();
    $buyer = User::factory()->create(['wallet_balance' => 1000]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'starting_price' => 10,
        'current_price' => 10,
        'buy_it_now_price' => 100,
        'buy_it_now_enabled' => true,
    ]);

    // Mock the services since we are not testing full payment integration
    $escrowService = Mockery::mock(EscrowService::class, function (MockInterface $mock) {
        $mock->shouldReceive('holdForBid')->once();
    });

    $paymentService = Mockery::mock(PaymentService::class, function (MockInterface $mock) use ($buyer, $auction) {
        $invoice = new Invoice();
        $invoice->id = 1;
        $mock->shouldReceive('captureWinnerPayment')->once()->andReturn($invoice);
    });

    app()->instance(EscrowService::class, $escrowService);
    app()->instance(PaymentService::class, $paymentService);

    $response = $this->actingAs($buyer)
        ->postJson(route('auctions.buy-it-now', $auction));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Purchase successful! You won this auction.',
            'invoice_id' => 1,
        ]);

    $auction->refresh();
    expect($auction->status)->toBe(Auction::STATUS_COMPLETED);
    expect($auction->winner_id)->toBe($buyer->id);
    expect($auction->win_method)->toBe('buy_it_now');
    expect((float) $auction->winning_bid_amount)->toEqual(100.0);

    Event::assertDispatched(AuctionClosed::class);
});

test('buy it now is unavailable if current price exceeds threshold', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['wallet_balance' => 1000]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'starting_price' => 10,
        'current_price' => 80, // Exceeds 75% of 100
        'buy_it_now_price' => 100,
        'buy_it_now_enabled' => true,
    ]);

    $response = $this->actingAs($buyer)
        ->postJson(route('auctions.buy-it-now', $auction));

    $response->assertStatus(422)
        ->assertJson(['message' => 'Buy It Now is no longer available for this auction.']);
});
