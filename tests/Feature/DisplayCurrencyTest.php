<?php

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

test('price component uses selected display currency and converts amount', function () {
    Cache::flush();

    config(['services.exchange_rate.fallback_rates' => [
        'EUR' => 0.5,
    ]]);

    app()->instance('display_currency', 'EUR');

    $html = Blade::render('<x-ui.price :amount="100" />');

    expect($html)->toContain('€50.00');
});
