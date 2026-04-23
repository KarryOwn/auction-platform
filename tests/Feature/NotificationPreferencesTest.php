<?php

use App\Models\User;

test('notification preferences page renders event settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('user.notification-preferences'));

    $response->assertOk();
    $response->assertSeeText('Outbid Alert');
    $response->assertSeeText('Auction Lost');
    $response->assertSee('preferences[auction_lost][email]', false);
});
