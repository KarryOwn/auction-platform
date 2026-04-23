<?php

use App\Jobs\GenerateAnalyticsSnapshot;
use App\Models\AnalyticsCategorySnapshot;
use App\Models\AnalyticsHourlyBidVolume;
use App\Models\AnalyticsSellerSnapshot;
use App\Models\Auction;
use App\Models\AuctionRating;
use App\Models\Bid;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function adminUser(): User
{
    return User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
}

test('generate analytics snapshot creates category hourly and seller aggregates', function () {
    $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
    $buyer = User::factory()->create(['role' => User::ROLE_USER]);
    $category = Category::create([
        'name' => 'Watches',
        'slug' => 'watches',
        'is_active' => true,
    ]);

    $completedAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'winner_id' => $buyer->id,
        'status' => Auction::STATUS_COMPLETED,
        'starting_price' => 100,
        'current_price' => 180,
        'winning_bid_amount' => 180,
        'closed_at' => now()->subDay()->setTime(14, 30),
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDay()->setTime(14, 35),
    ]);
    $completedAuction->categories()->attach($category->id, ['is_primary' => true]);

    $cancelledAuction = Auction::factory()->cancelled()->create([
        'user_id' => $seller->id,
        'starting_price' => 90,
        'current_price' => 90,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDay()->setTime(10, 0),
    ]);
    $cancelledAuction->categories()->attach($category->id, ['is_primary' => false]);

    $firstBid = Bid::create([
        'auction_id' => $completedAuction->id,
        'user_id' => $buyer->id,
        'amount' => 150,
        'bid_type' => Bid::TYPE_MANUAL,
    ]);
    DB::table('bids')->where('id', $firstBid->id)->update([
        'created_at' => now()->subDay()->setTime(14, 15),
        'updated_at' => now()->subDay()->setTime(14, 15),
    ]);

    $secondBid = Bid::create([
        'auction_id' => $completedAuction->id,
        'user_id' => $buyer->id,
        'amount' => 180,
        'bid_type' => Bid::TYPE_MANUAL,
    ]);
    DB::table('bids')->where('id', $secondBid->id)->update([
        'created_at' => now()->subDay()->setTime(14, 45),
        'updated_at' => now()->subDay()->setTime(14, 45),
    ]);

    AuctionRating::create([
        'auction_id' => $completedAuction->id,
        'rater_id' => $buyer->id,
        'ratee_id' => $seller->id,
        'role' => 'seller',
        'score' => 5,
        'comment' => 'Great seller',
    ]);

    (new GenerateAnalyticsSnapshot())->handle();

    $categorySnapshot = AnalyticsCategorySnapshot::firstOrFail();
    expect($categorySnapshot->category_id)->toBe($category->id);
    expect($categorySnapshot->completed_auctions)->toBe(1);
    expect($categorySnapshot->cancelled_auctions)->toBe(1);
    expect((float) $categorySnapshot->avg_final_price)->toBe(180.0);
    expect((float) $categorySnapshot->avg_starting_price)->toBe(100.0);
    expect((float) $categorySnapshot->price_appreciation_pct)->toBe(80.0);
    expect($categorySnapshot->total_bids)->toBe(2);
    expect($categorySnapshot->unique_bidders)->toBe(1);

    $hourlySnapshot = AnalyticsHourlyBidVolume::firstOrFail();
    expect($hourlySnapshot->hour_of_day)->toBe(14);
    expect($hourlySnapshot->bid_count)->toBe(2);
    expect($hourlySnapshot->unique_bidders)->toBe(1);
    expect($hourlySnapshot->unique_auctions)->toBe(1);

    $sellerSnapshot = AnalyticsSellerSnapshot::firstOrFail();
    expect($sellerSnapshot->user_id)->toBe($seller->id);
    expect($sellerSnapshot->completed_sales)->toBe(1);
    expect((float) $sellerSnapshot->gross_revenue)->toBe(180.0);
    expect((float) $sellerSnapshot->avg_sale_price)->toBe(180.0);
    expect((float) $sellerSnapshot->avg_rating)->toBe(5.0);
    expect($sellerSnapshot->total_bids_received)->toBe(2);
});

test('admin analytics endpoints return aggregated data', function () {
    $admin = adminUser();
    $seller = User::factory()->create(['role' => User::ROLE_SELLER, 'seller_slug' => 'top-seller']);
    $buyer = User::factory()->create(['role' => User::ROLE_USER, 'wallet_balance' => 250]);
    $category = Category::create([
        'name' => 'Sneakers',
        'slug' => 'sneakers',
        'is_active' => true,
    ]);

    AnalyticsCategorySnapshot::create([
        'category_id' => $category->id,
        'report_date' => now()->subDays(2)->toDateString(),
        'total_auctions' => 4,
        'completed_auctions' => 3,
        'cancelled_auctions' => 1,
        'sell_through_rate' => 0.75,
        'avg_final_price' => 220,
        'avg_starting_price' => 150,
        'price_appreciation_pct' => 46.6667,
        'total_bids' => 18,
        'unique_bidders' => 9,
    ]);

    AnalyticsHourlyBidVolume::create([
        'report_date' => now()->subDays(2)->toDateString(),
        'hour_of_day' => 20,
        'day_of_week' => 'friday',
        'bid_count' => 12,
        'unique_bidders' => 7,
        'unique_auctions' => 4,
    ]);

    AnalyticsSellerSnapshot::create([
        'user_id' => $seller->id,
        'report_date' => now()->subDays(2)->toDateString(),
        'active_listings' => 5,
        'completed_sales' => 3,
        'gross_revenue' => 660,
        'avg_sale_price' => 220,
        'avg_rating' => 4.8,
        'total_bids_received' => 21,
    ]);

    $wonAuction = Auction::factory()->completed()->create([
        'winner_id' => $buyer->id,
        'winning_bid_amount' => 300,
        'closed_at' => now()->subDays(2),
    ]);

    $buyerBid = Bid::create([
        'auction_id' => $wonAuction->id,
        'user_id' => $buyer->id,
        'amount' => 300,
        'bid_type' => Bid::TYPE_MANUAL,
    ]);
    DB::table('bids')->where('id', $buyerBid->id)->update([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $categoryResponse = $this->actingAs($admin)->getJson(route('admin.analytics.categories', ['days' => 30]));
    $categoryResponse->assertOk()->assertJsonPath('data.0.category.slug', 'sneakers');

    $timingResponse = $this->actingAs($admin)->getJson(route('admin.analytics.bid-timing', ['days' => 30]));
    $timingResponse->assertOk()
        ->assertJsonPath('peak_hour', 20)
        ->assertJsonPath('peak_day', 'friday');

    $leaderboardResponse = $this->actingAs($admin)->getJson(route('admin.analytics.leaderboard', ['period' => 30]));
    $leaderboardResponse->assertOk()->assertJsonPath('data.0.user.seller_slug', 'top-seller');

    $buyersResponse = $this->actingAs($admin)->getJson(route('admin.analytics.buyers', ['days' => 30]));
    $buyersResponse->assertOk()->assertJsonPath('data.0.id', $buyer->id);

    $buyerReportResponse = $this->actingAs($admin)->getJson(route('admin.analytics.buyers.report', $buyer));
    $buyerReportResponse->assertOk()
        ->assertJsonPath('user_id', $buyer->id)
        ->assertJsonPath('auctions_won', 1)
        ->assertJsonPath('total_spent', 300);
});

test('admin analytics heatmap has mobile horizontal scroll wrapper', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertSee('data-analytics-heatmap-scroll', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('min-w-[56rem]', false);
});

test('admin analytics dashboard shows export buttons', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertSeeText('Advanced exports')
        ->assertSeeText('Export Categories')
        ->assertSeeText('Export Heatmap')
        ->assertSeeText('Export Sellers')
        ->assertSeeText('Export Buyers')
        ->assertSee(route('admin.analytics.export', ['report' => 'categories', 'days' => 30]), false);
});

test('admin can export analytics csv reports', function () {
    $admin = adminUser();
    $seller = User::factory()->create(['role' => User::ROLE_SELLER, 'name' => 'Export Seller', 'seller_slug' => 'export-seller']);
    $buyer = User::factory()->create(['role' => User::ROLE_USER, 'name' => 'Export Buyer', 'wallet_balance' => 125]);
    $category = Category::create([
        'name' => 'Books',
        'slug' => 'books',
        'is_active' => true,
    ]);

    AnalyticsCategorySnapshot::create([
        'category_id' => $category->id,
        'report_date' => now()->subDay()->toDateString(),
        'total_auctions' => 2,
        'completed_auctions' => 1,
        'cancelled_auctions' => 0,
        'sell_through_rate' => 0.5,
        'avg_final_price' => 80,
        'avg_starting_price' => 50,
        'price_appreciation_pct' => 60,
        'total_bids' => 4,
        'unique_bidders' => 2,
    ]);
    AnalyticsSellerSnapshot::create([
        'user_id' => $seller->id,
        'report_date' => now()->subDay()->toDateString(),
        'active_listings' => 3,
        'completed_sales' => 1,
        'gross_revenue' => 80,
        'avg_sale_price' => 80,
        'avg_rating' => 4.5,
        'total_bids_received' => 4,
    ]);
    AnalyticsHourlyBidVolume::create([
        'report_date' => now()->subDay()->toDateString(),
        'hour_of_day' => 11,
        'day_of_week' => 'monday',
        'bid_count' => 4,
        'unique_bidders' => 2,
        'unique_auctions' => 1,
    ]);
    $auction = Auction::factory()->completed()->create([
        'winner_id' => $buyer->id,
        'winning_bid_amount' => 80,
        'closed_at' => now()->subDay(),
    ]);
    $bid = Bid::create([
        'auction_id' => $auction->id,
        'user_id' => $buyer->id,
        'amount' => 80,
        'bid_type' => Bid::TYPE_MANUAL,
    ]);
    DB::table('bids')->where('id', $bid->id)->update([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $categories = $this->actingAs($admin)->get(route('admin.analytics.export', ['report' => 'categories']));
    $categories->assertOk();
    expect($categories->headers->get('content-disposition'))->toContain('admin-analytics-categories');
    expect($categories->streamedContent())->toContain('Books');

    $leaderboard = $this->actingAs($admin)->get(route('admin.analytics.export', ['report' => 'leaderboard']));
    $leaderboard->assertOk();
    expect($leaderboard->streamedContent())->toContain('Export Seller');

    $heatmap = $this->actingAs($admin)->get(route('admin.analytics.export', ['report' => 'bid-timing']));
    $heatmap->assertOk();
    expect($heatmap->streamedContent())->toContain('monday');

    $buyers = $this->actingAs($admin)->get(route('admin.analytics.export', ['report' => 'buyers']));
    $buyers->assertOk();
    expect($buyers->streamedContent())->toContain('Export Buyer');
});
