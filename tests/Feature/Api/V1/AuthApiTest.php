<?php

use App\Models\User;

test('api auth token endpoint issues token for valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'api-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'pest-suite',
        'abilities' => ['auctions:read', 'bids:place'],
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'abilities'])
        ->assertJson(['token_type' => 'Bearer']);
});

test('api auth token endpoint rejects invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'invalid-credentials@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'pest-suite',
    ]);

    $response->assertStatus(422);
});
