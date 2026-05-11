<?php

use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

test('guest can switch display currency with a cookie', function () {
    $response = $this->post(route('preferences.currency'), [
        'currency' => 'EUR',
    ]);

    $response->assertRedirect();
    $response->assertCookie('display_currency', 'EUR');
});

test('authenticated user currency switch persists to preferences and cookie', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('preferences.currency'), [
        'currency' => 'JPY',
    ]);

    $response->assertRedirect();
    $response->assertCookie('display_currency', 'JPY');

    expect($user->fresh()->userPreference?->display_currency)->toBe('JPY');
});

test('display currency middleware resolves cookie currency for guest requests', function () {
    Route::middleware('web')->get('/_test/display-currency', fn () => response(display_currency()));

    $response = $this->withCookie('display_currency', 'GBP')->get('/_test/display-currency');

    $response->assertOk();
    $response->assertSeeText('GBP');
});

test('notification preferences update persists display currency', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('user.notification-preferences.update'), [
        'preferences' => User::DEFAULT_NOTIFICATION_PREFERENCES,
        'locale' => 'en',
        'display_currency' => 'VND',
    ]);

    $response->assertRedirect(route('user.notification-preferences'));
    $response->assertCookie('display_currency', 'VND');

    expect($user->fresh()->userPreference?->display_currency)->toBe('VND');
});

test('selected display currency converts prices on auction browse and detail pages', function () {
    useSqlBiddingEngine();
    Cache::flush();

    config(['services.exchange_rate.fallback_rates' => [
        'EUR' => 0.5,
    ]]);

    $seller = createSeller();
    $auction = createActiveAuction($seller, [
        'title' => 'Display Currency Scope Watch',
        'starting_price' => 100,
        'current_price' => 100,
    ]);

    $this->withCookie('display_currency', 'EUR')
        ->get(route('auctions.index'))
        ->assertOk()
        ->assertSee('Display Currency Scope Watch')
        ->assertSee('€50.00')
        ->assertDontSee('$100.00');

    $this->withCookie('display_currency', 'EUR')
        ->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertSee('€50.00');
});

test('selected display currency converts dashboard and wallet balances', function () {
    Cache::flush();

    config(['services.exchange_rate.fallback_rates' => [
        'EUR' => 0.5,
    ]]);

    $user = User::factory()->create([
        'wallet_balance' => 100,
        'held_balance' => 25,
        'pending_payout_balance' => 10,
    ]);
    WalletTransaction::factory()->create([
        'user_id' => $user->id,
        'type' => WalletTransaction::TYPE_DEPOSIT,
        'amount' => 100,
        'balance_after' => 100,
    ]);

    $this->actingAs($user)
        ->withCookie('display_currency', 'EUR')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('€50.00');

    $this->actingAs($user)
        ->withCookie('display_currency', 'EUR')
        ->get(route('user.wallet'))
        ->assertOk()
        ->assertSee('€50.00')
        ->assertSee('€12.50')
        ->assertSee('€5.00');
});

test('price component defaults to platform currency when display currency is selected', function () {
    app()->instance('display_currency', 'EUR');

    $html = Blade::render('<x-ui.price :amount="100" />');

    expect($html)->toContain('$100.00')
        ->not->toContain('€');
});

test('price component can opt in to selected display currency and converts amount', function () {
    Cache::flush();

    config(['services.exchange_rate.fallback_rates' => [
        'EUR' => 0.5,
    ]]);

    app()->instance('display_currency', 'EUR');

    $html = Blade::render('<x-ui.price :amount="100" use-display-currency />');

    expect($html)->toContain('€50.00');
});
