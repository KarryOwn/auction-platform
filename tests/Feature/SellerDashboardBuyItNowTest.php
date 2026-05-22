<?php

use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('seller dashboard counts buy it now sales in gross revenue and recent activity', function () {
    Cache::flush();

    $seller = createSeller();
    $buyer = User::factory()->create(['name' => 'Instant Buyer']);

    Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Vintage Camera',
        'status' => Auction::STATUS_COMPLETED,
        'winner_id' => $buyer->id,
        'winning_bid_amount' => 120.00,
        'current_price' => 120.00,
        'win_method' => 'buy_it_now',
        'reserve_met' => false,
        'closed_at' => now(),
        'end_time' => now()->subMinute(),
    ]);

    $response = $this->actingAs($seller)->get(route('seller.dashboard'));

    $response->assertOk()
        ->assertSee('$120.00')
        ->assertSee('Instant Buyer bought Vintage Camera for $120.00 with Buy It Now');
});
