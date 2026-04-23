<?php

use App\Models\SellerApplication;
use App\Models\User;
use App\Notifications\SellerApplicationSubmittedNotification;

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

test('seller application submitted notification links to admin review page', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $application = SellerApplication::create([
        'user_id' => $user->id,
        'reason' => 'I sell authenticated collectibles and can fulfill orders quickly.',
        'experience' => 'Five years of online marketplace selling.',
        'status' => SellerApplication::STATUS_PENDING,
    ]);

    $payload = (new SellerApplicationSubmittedNotification($application))->toArray(User::factory()->make([
        'role' => User::ROLE_ADMIN,
    ]));

    expect($payload['type'])->toBe('seller_application_submitted')
        ->and($payload['application_id'])->toBe($application->id)
        ->and($payload['message'])->toBe('Seller application has been submitted and needs review.')
        ->and($payload['url'])->toBe(route('admin.seller-applications.show', $application));
});
