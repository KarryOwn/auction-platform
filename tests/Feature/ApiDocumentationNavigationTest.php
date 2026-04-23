<?php

use App\Models\User;

test('homepage footer links to api documentation and developer tools', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Developers')
        ->assertSeeText('API Documentation')
        ->assertSee(url(config('l5-swagger.documentations.v1.routes.api')), false)
        ->assertSee(route('user.api-tokens.index'), false)
        ->assertSee(route('user.webhooks.index'), false);
});

test('api token settings includes developer navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('user.api-tokens.index'))
        ->assertOk()
        ->assertSeeText('Developer navigation')
        ->assertSeeText('Open Swagger docs')
        ->assertSeeText('Webhook endpoints')
        ->assertSee(url(config('l5-swagger.documentations.v1.routes.api')), false)
        ->assertSee(route('user.webhooks.index'), false);
});

test('swagger vendor view includes api documentation navigation shell', function () {
    $html = view('vendor.l5-swagger.index', [
        'documentationTitle' => 'BidFlow API',
        'documentation' => 'v1',
        'urlsToDocs' => ['BidFlow API' => '/docs/api-docs.json'],
        'operationsSorter' => null,
        'configUrl' => null,
        'validatorUrl' => null,
        'useAbsolutePath' => false,
    ])->render();

    expect($html)->toContain('data-api-docs-navigation')
        ->toContain('BidFlow API Documentation')
        ->toContain('Create API Token')
        ->toContain('Webhook Endpoints');
});
