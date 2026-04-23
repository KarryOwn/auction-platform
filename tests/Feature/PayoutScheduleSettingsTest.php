<?php

test('wallet displays payout schedule settings and pending payout balance', function () {
    $seller = createSeller([
        'wallet_balance' => 500,
        'held_balance' => 100,
        'pending_payout_balance' => 75,
        'payout_schedule' => 'weekly',
        'payout_schedule_day' => 'friday',
        'stripe_connect_account_id' => 'acct_test',
        'stripe_connect_onboarded' => true,
    ]);

    $this->actingAs($seller)
        ->get(route('user.wallet'))
        ->assertOk()
        ->assertSeeText('Pending Payouts')
        ->assertSeeText('$75.00')
        ->assertSeeText('Payout schedule settings')
        ->assertSeeText('Weekly automatic payouts');
});

test('user can update payout schedule settings', function () {
    $seller = createSeller([
        'payout_schedule' => 'manual',
        'payout_schedule_day' => null,
    ]);

    $response = $this->actingAs($seller)->put(route('user.payout-settings.update'), [
        'payout_schedule' => 'monthly',
        'payout_schedule_day' => '15',
    ]);

    $response->assertRedirect(route('user.wallet'));

    $seller->refresh();
    expect($seller->payout_schedule)->toBe('monthly');
    expect($seller->payout_schedule_day)->toBe('15');
});
