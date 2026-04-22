<?php

use App\Models\User;
use App\Support\ApiAbilities;
use Laravel\Sanctum\PersonalAccessToken;

test('api token index loads for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('user.api-tokens.index'))
        ->assertOk()
        ->assertSeeText('API Tokens');
});

test('user can create api token from dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('user.api-tokens.store'), [
        'name' => 'Local script',
        'abilities' => [ApiAbilities::AUCTIONS_READ, ApiAbilities::WATCHLIST_WRITE],
    ]);

    $response->assertRedirect(route('user.api-tokens.index'));
    $response->assertSessionHas('new_api_token');

    $token = $user->tokens()->latest()->first();

    expect($token)->not->toBeNull();
    expect($token->name)->toBe('Local script');
    expect($token->abilities)->toBe([ApiAbilities::AUCTIONS_READ, ApiAbilities::WATCHLIST_WRITE]);
});

test('user can revoke own api token from dashboard', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Old integration', [ApiAbilities::AUCTIONS_READ]);
    $plainTextId = explode('|', $token->plainTextToken)[0];
    $accessToken = PersonalAccessToken::findOrFail($plainTextId);

    $response = $this->actingAs($user)->delete(route('user.api-tokens.destroy', $accessToken));

    $response->assertRedirect(route('user.api-tokens.index'));
    expect(PersonalAccessToken::find($accessToken->id))->toBeNull();
});
