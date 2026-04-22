<?php

use App\Models\User;

test('public profile shows block action for another authenticated user', function () {
    $viewer = User::factory()->create();
    $profileUser = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('users.show', $profileUser))
        ->assertOk()
        ->assertSeeText('Block User');
});

test('profile settings shows blocked users list', function () {
    $viewer = User::factory()->create();
    $blockedA = User::factory()->create(['name' => 'Blocked Alpha']);
    $blockedB = User::factory()->create(['name' => 'Blocked Beta']);

    $viewer->blockedUsers()->attach([$blockedA->id, $blockedB->id]);

    $this->actingAs($viewer)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSeeText('Blocked Users')
        ->assertSeeText('Blocked Alpha')
        ->assertSeeText('Blocked Beta')
        ->assertSeeText('Unblock');
});

test('blocked users can be unblocked from profile settings form flow', function () {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->blockedUsers()->attach($blocked->id);

    $response = $this->actingAs($viewer)->post(route('users.block', $blocked));

    $response->assertRedirect();
    expect($viewer->fresh()->hasBlocked($blocked->id))->toBeFalse();
});
