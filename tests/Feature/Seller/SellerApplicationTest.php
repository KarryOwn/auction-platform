<?php

use App\Models\SellerApplication;
use App\Models\User;

test('user can submit seller application', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->post(route('seller.apply.submit'), [
        'reason' => str_repeat('I have experience selling collectible items safely. ', 2),
        'experience' => 'Three years of marketplace selling.',
        'accept_terms' => true,
    ]);

    $response->assertRedirect(route('seller.application.status'));

    expect(SellerApplication::where('user_id', $user->id)->exists())->toBeTrue();
    expect($user->fresh()->seller_application_status)->toBe('pending');
});

test('verified seller is redirected away from seller apply form', function () {
    $seller = createSeller();

    $this->actingAs($seller)
        ->get(route('seller.apply.form'))
        ->assertRedirect(route('seller.dashboard'));
});
