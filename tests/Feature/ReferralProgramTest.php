<?php

use App\Models\ReferralReward;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;

test('new user gets unique referral code', function () {
    $user = User::factory()->create();
    expect($user->referral_code)->not->toBeNull();
    expect(strlen($user->referral_code))->toBe(8);
});

test('middleware captures ref code in session', function () {
    $response = $this->get('/register?ref=ABC12345');
    $response->assertSessionHas('referral_code', 'ABC12345');
});

test('referral service links users and credits rewards', function () {
    $referrer = User::factory()->create(['referral_code' => 'XYZ98765', 'wallet_balance' => 0]);
    $newUser = User::factory()->create(['wallet_balance' => 0]);

    $walletService = app(WalletService::class);
    $service = new ReferralService($walletService);

    // This creates the link and triggers the immediate credit
    $service->linkReferral($newUser, 'XYZ98765');

    $referrer->refresh();
    $newUser->refresh();

    expect($newUser->referred_by_user_id)->toBe($referrer->id);
    
    $reward = ReferralReward::where('referee_id', $newUser->id)->first();
    expect($reward)->not->toBeNull();
    expect($reward->referrer_id)->toBe($referrer->id);
    expect($reward->status)->toBe('credited');

    // Default config values: 5.0 for referrer, 2.5 for referee
    expect((float) $referrer->wallet_balance)->toBe(5.0);
    expect((float) $newUser->wallet_balance)->toBe(2.5);
});

test('cannot refer oneself', function () {
    $newUser = User::factory()->create(['referral_code' => 'MYCODE12']);
    
    $service = app(ReferralService::class);
    $service->linkReferral($newUser, 'MYCODE12');

    expect($newUser->fresh()->referred_by_user_id)->toBeNull();
    expect(ReferralReward::count())->toBe(0);
});

test('invalid code does nothing', function () {
    $newUser = User::factory()->create();
    
    $service = app(ReferralService::class);
    $service->linkReferral($newUser, 'INVALID');

    expect($newUser->fresh()->referred_by_user_id)->toBeNull();
    expect(ReferralReward::count())->toBe(0);
});
