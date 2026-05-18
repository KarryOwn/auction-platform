<?php

use App\Models\User;

test('notification preferences page renders event settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('user.notification-preferences'));

    $response->assertOk();
    $response->assertSeeText('Outbid Alert');
    $response->assertSeeText('Auction Lost');
    $response->assertSeeText('Messages');
    $response->assertSee('preferences[auction_lost][email]', false);
    $response->assertSee('preferences[messages][email]', false);
});

test('notification preferences save message email setting', function () {
    $user = User::factory()->create();
    $preferences = User::DEFAULT_NOTIFICATION_PREFERENCES;
    $preferences['messages']['email'] = false;

    $this->actingAs($user)
        ->put(route('user.notification-preferences.update'), [
            'preferences' => $preferences,
        ])
        ->assertRedirect(route('user.notification-preferences'));

    expect($user->fresh()->notification_preferences['messages']['email'])->toBeFalse()
        ->and($user->fresh()->notification_preferences['messages']['database'])->toBeTrue();
});
