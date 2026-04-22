<?php

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\AuctionWatcher;
use App\Models\Bid;
use App\Models\Category;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\ApiAbilities;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    app()->instance(BiddingStrategy::class, new class implements BiddingStrategy {
        public function placeBid(Auction $auction, User $user, float $amount, array $meta = []): Bid
        {
            $bid = Bid::create([
                'auction_id' => $auction->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'bid_type' => Bid::TYPE_MANUAL,
                'previous_amount' => $auction->current_price,
                'ip_address' => $meta['ip_address'] ?? '127.0.0.1',
                'user_agent' => $meta['user_agent'] ?? 'Pest',
                'is_snipe_bid' => false,
            ]);

            $auction->forceFill([
                'current_price' => $amount,
                'bid_count' => $auction->bid_count + 1,
            ])->save();

            return $bid;
        }

        public function getCurrentPrice(Auction $auction): float
        {
            return (float) $auction->current_price;
        }

        public function initializePrice(Auction $auction): void
        {
            // No-op for API tests.
        }

        public function cleanup(Auction $auction): void
        {
            // No-op for API tests.
        }
    });
});

test('public api v1 can list auctions and show category filtered results', function () {
    $category = Category::create([
        'name' => 'Watches',
        'slug' => 'watches',
        'is_active' => true,
    ]);

    $matching = Auction::factory()->create([
        'title' => 'Vintage Omega Seamaster',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
    ]);
    $matching->categories()->sync([$category->id => ['is_primary' => true]]);

    $other = Auction::factory()->create([
        'title' => 'Camera Lens Kit',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
    ]);

    $index = $this->getJson(route('api.v1.auctions.index', [
        'q' => 'Omega',
        'category_id' => $category->id,
    ]));

    $index->assertOk()
        ->assertJsonFragment(['title' => 'Vintage Omega Seamaster'])
        ->assertJsonMissing(['title' => 'Camera Lens Kit']);

    $show = $this->getJson(route('api.v1.auctions.show', $matching));

    $show->assertOk()
        ->assertJsonPath('data.id', $matching->id)
        ->assertJsonPath('data.seller.id', $matching->user_id);
});

test('public api v1 categories endpoints return category collections and detail', function () {
    $parent = Category::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
    ]);
    $child = Category::create([
        'name' => 'Phones',
        'slug' => 'phones',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    $index = $this->getJson(route('api.v1.categories.index'));
    $index->assertOk()->assertJsonFragment(['name' => 'Electronics']);

    $show = $this->getJson(route('api.v1.categories.show', $child));
    $show->assertOk()
        ->assertJsonPath('data.id', $child->id)
        ->assertJsonFragment(['name' => 'Phones']);
});

test('api v1 can issue and revoke sanctum tokens with abilities', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret-password'),
    ]);

    $issue = $this->postJson(route('api.v1.auth.token'), [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'iPhone 21',
        'abilities' => [ApiAbilities::AUCTIONS_READ, ApiAbilities::PROFILE_READ],
    ]);

    $issue->assertOk()
        ->assertJsonPath('abilities.0', ApiAbilities::AUCTIONS_READ)
        ->assertJsonStructure(['token', 'token_type', 'abilities']);

    [$tokenId] = explode('|', $issue->json('token'));

    expect(PersonalAccessToken::findToken($issue->json('token')))->not->toBeNull();

    $revoke = $this->withToken($issue->json('token'))->deleteJson(route('api.v1.auth.revoke'));

    $revoke->assertOk()->assertJson(['message' => 'Token revoked.']);
    expect(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse();
});

test('api v1 bid endpoint enforces token ability and can place a bid', function () {
    $user = User::factory()->create([
        'wallet_balance' => 500,
        'held_balance' => 0,
    ]);
    $auction = Auction::factory()->create([
        'status' => Auction::STATUS_ACTIVE,
        'current_price' => 100,
        'min_bid_increment' => 5,
        'end_time' => now()->addHour(),
    ]);

    Sanctum::actingAs($user, [ApiAbilities::AUCTIONS_READ]);

    $forbidden = $this->postJson(route('api.v1.bids.store', $auction), [
        'amount' => 125,
    ]);

    $forbidden->assertStatus(403)
        ->assertJsonPath('error', 'Token lacks bids:place scope.');

    Sanctum::actingAs($user, [ApiAbilities::BIDS_PLACE, ApiAbilities::BIDS_READ]);

    $success = $this->postJson(route('api.v1.bids.store', $auction), [
        'amount' => 125,
    ]);

    $success->assertStatus(201)
        ->assertJsonPath('data.amount', 125)
        ->assertJsonPath('meta.new_price', 125);

    $index = $this->getJson(route('api.v1.bids.index', $auction));

    $index->assertOk()
        ->assertJsonFragment(['amount' => 125.0]);
});

test('api v1 profile wallet and watch endpoints require matching abilities', function () {
    $user = User::factory()->create([
        'wallet_balance' => 250,
        'held_balance' => 40,
    ]);
    $auction = Auction::factory()->create([
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addHour(),
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $user->id,
        'amount' => 50,
        'balance_after' => 250,
    ]);

    Sanctum::actingAs($user, [ApiAbilities::WATCHLIST_WRITE]);
    $watch = $this->postJson(route('api.v1.watch.toggle', $auction));

    $watch->assertOk()->assertJson(['watching' => true]);
    expect(AuctionWatcher::query()->where('user_id', $user->id)->where('auction_id', $auction->id)->exists())->toBeTrue();

    Sanctum::actingAs($user, [ApiAbilities::PROFILE_READ, ApiAbilities::BIDS_READ, ApiAbilities::WALLET_READ]);

    $profile = $this->getJson(route('api.v1.profile.show'));
    $profile->assertOk()->assertJsonPath('data.id', $user->id);

    $wallet = $this->getJson(route('api.v1.profile.wallet'));
    $wallet->assertOk()
        ->assertJsonPath('data.wallet_balance', 250)
        ->assertJsonPath('data.available_balance', 210);

    $notifications = $this->getJson(route('api.v1.profile.notifications'));
    $notifications->assertOk();
});

test('api v1 returns standardized validation and authentication errors', function () {
    $unauthenticated = $this->getJson(route('api.v1.profile.show'));
    $unauthenticated->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthenticated.',
            'code' => 401,
        ]);

    $validation = $this->postJson(route('api.v1.auth.token'), [
        'email' => 'not-an-email',
    ]);

    $validation->assertStatus(422)
        ->assertJsonPath('error', 'Validation failed.')
        ->assertJsonStructure(['details' => ['email', 'password', 'device_name']]);
});
