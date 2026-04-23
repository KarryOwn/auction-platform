<?php

use App\Models\Auction;
use App\Models\User;
use App\Models\WalletTransaction;

test('authenticated user can view credits store with available balance', function () {
    $seller = createSeller(['wallet_balance' => 40, 'held_balance' => 5]);
    createActiveAuction($seller, ['title' => 'Vintage Camera']);

    $this->actingAs($seller)
        ->get(route('user.credits.index'))
        ->assertOk()
        ->assertSeeText('Credits & Power-Ups')
        ->assertSeeText('$35.00')
        ->assertSeeText('Spotlight Boost')
        ->assertSeeText('Vintage Camera');
});

test('seller can buy power up for owned active auction using wallet credits', function () {
    $seller = createSeller(['wallet_balance' => 40, 'held_balance' => 0]);
    $auction = createActiveAuction($seller, [
        'title' => 'Signed Poster',
        'is_featured' => false,
        'featured_until' => null,
    ]);

    $response = $this->actingAs($seller)->post(route('user.credits.power-ups.store'), [
        'power_up' => 'homepage',
        'auction_id' => $auction->id,
    ]);

    $response->assertRedirect(route('user.credits.index'));

    $seller->refresh();
    $auction->refresh();

    expect((float) $seller->wallet_balance)->toBe(28.0);
    expect($auction->is_featured)->toBeTrue();
    expect($auction->featured_until)->not->toBeNull();
    expect($auction->featured_position)->toBe(10);

    $transaction = WalletTransaction::query()->latest()->first();
    expect($transaction->type)->toBe(WalletTransaction::TYPE_WITHDRAWAL);
    expect((float) $transaction->amount)->toBe(12.0);
    expect($transaction->reference_type)->toBe($auction->getMorphClass());
    expect($transaction->reference_id)->toBe($auction->id);
});

test('power up purchase rejects insufficient credits and other sellers auctions', function () {
    $seller = createSeller(['wallet_balance' => 4]);
    $otherSeller = createSeller();
    $ownAuction = createActiveAuction($seller);
    $otherAuction = createActiveAuction($otherSeller);

    $this->actingAs($seller)
        ->post(route('user.credits.power-ups.store'), [
            'power_up' => 'spotlight',
            'auction_id' => $ownAuction->id,
        ])
        ->assertSessionHasErrors('power_up');

    $this->actingAs($seller)
        ->post(route('user.credits.power-ups.store'), [
            'power_up' => 'spotlight',
            'auction_id' => $otherAuction->id,
        ])
        ->assertSessionHasErrors('auction_id');

    expect((float) $seller->fresh()->wallet_balance)->toBe(4.0);
    expect(Auction::find($ownAuction->id)->is_featured)->toBeFalse();
});

test('credits store exposes stripe power up checkout', function () {
    $seller = createSeller(['wallet_balance' => 0]);
    createActiveAuction($seller, ['title' => 'Stripe Boost Auction']);

    $this->actingAs($seller)
        ->get(route('user.credits.index'))
        ->assertOk()
        ->assertSeeText('Pay with Stripe')
        ->assertSee(route('user.credits.power-ups.stripe'), false);
});

test('stripe power up checkout redirects and success applies boost', function () {
    $seller = createSeller(['wallet_balance' => 0]);
    $auction = createActiveAuction($seller, [
        'title' => 'Checkout Boost Auction',
        'is_featured' => false,
        'featured_until' => null,
    ]);

    $checkout = $this->actingAs($seller)->post(route('user.credits.power-ups.stripe'), [
        'power_up' => 'spotlight',
        'auction_id' => $auction->id,
    ]);

    $checkout->assertRedirect('https://checkout.stripe.test/power-up');
    expect($auction->fresh()->is_featured)->toBeFalse();

    $success = $this->actingAs($seller)->get(route('user.credits.stripe.success'));
    $success->assertRedirect(route('user.credits.index'));

    $auction->refresh();
    expect($auction->is_featured)->toBeTrue();
    expect($auction->featured_position)->toBe(30);
    expect((float) $seller->fresh()->wallet_balance)->toBe(0.0);
});
