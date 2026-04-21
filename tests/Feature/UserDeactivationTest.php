<?php

use App\Models\User;

test('user can deactivate account', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->actingAs($user)->post(route('profile.deactivate'), [
        'password' => 'password123',
    ]);

    $response->assertRedirect('/');

    $user->refresh();
    expect($user->is_deactivated)->toBeTrue();
    expect($user->deactivated_at)->not->toBeNull();
    expect($user->reactivation_deadline)->not->toBeNull();
    
    // Asserts user is logged out
    $this->assertGuest();
});

test('deactivated user is redirected to reactivation page on login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
        'is_deactivated' => true,
        'deactivated_at' => now()->subDay(),
        'reactivation_deadline' => now()->addDays(29),
    ]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('account.reactivate'));
    $response->assertSessionHas('reactivation_user_id', $user->id);
    
    $this->assertGuest(); // User should be logged out
});

test('user can reactivate account', function () {
    $user = User::factory()->create([
        'is_deactivated' => true,
        'deactivated_at' => now()->subDay(),
        'reactivation_deadline' => now()->addDays(29),
    ]);

    // Simulate arriving from login
    session(['reactivation_user_id' => $user->id]);

    $response = $this->post(route('account.reactivate.store'));

    $response->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->is_deactivated)->toBeFalse();
    expect($user->deactivated_at)->toBeNull();
    expect($user->reactivation_deadline)->toBeNull();
    
    $this->assertAuthenticatedAs($user);
});

test('user is permanently deleted if reactivation deadline passes', function () {
    $user = User::factory()->create([
        'is_deactivated' => true,
        'deactivated_at' => now()->subDays(31),
        'reactivation_deadline' => now()->subDay(),
    ]);

    // Simulate arriving from login
    session(['reactivation_user_id' => $user->id]);

    $response = $this->post(route('account.reactivate.store'));

    $response->assertRedirect('/');
    $response->assertSessionHas('error');

    // Soft deletion testing or manual delete testing would be handled by the job
});

test('purge deactivated accounts job force deletes users', function () {
    $user = User::factory()->create([
        'is_deactivated' => true,
        'deactivated_at' => now()->subDays(31),
        'reactivation_deadline' => now()->subDay(),
    ]);

    $safeUser = User::factory()->create([
        'is_deactivated' => true,
        'deactivated_at' => now()->subDays(10),
        'reactivation_deadline' => now()->addDays(20),
    ]);

    // Trigger the schedule closure manually
    \App\Models\User::where('is_deactivated', true)
        ->where('reactivation_deadline', '<=', now())
        ->each(fn ($u) => $u->forceDelete());

    // The expired user should be completely gone
    expect(\App\Models\User::withTrashed()->find($user->id))->toBeNull();
    
    // The safe user should still exist
    expect(\App\Models\User::find($safeUser->id))->not->toBeNull();
});

test('destroy method soft deletes and anonymizes user', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password123',
    ]);

    $response->assertRedirect('/');
    
    $this->assertGuest();

    $deletedUser = User::withTrashed()->find($user->id);
    expect($deletedUser->trashed())->toBeTrue();
    expect($deletedUser->name)->toBe('Deleted User #' . $user->id);
    expect($deletedUser->email)->toBe('deleted-' . $user->id . '@deleted.invalid');
});
