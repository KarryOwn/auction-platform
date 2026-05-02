<?php

use App\Models\User;

test('admin can ban and unban a user', function () {
    $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_USER, 'is_banned' => false]);

    $ban = $this->actingAs($staff)->postJson(route('admin.users.ban', $target), [
        'reason' => 'Testing moderation flow',
    ]);

    $ban->assertOk();
    expect($target->fresh()->is_banned)->toBeTrue();

    $unban = $this->actingAs($staff)->postJson(route('admin.users.unban', $target));
    $unban->assertOk();

    expect($target->fresh()->is_banned)->toBeFalse();
});

test('admin cannot assign removed moderator role', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($admin)
        ->patchJson(route('admin.users.role', $target), [
            'role' => 'moderator',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($target->fresh()->role)->toBe(User::ROLE_USER);
});

test('non staff user cannot access admin users index', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertStatus(403);
});
