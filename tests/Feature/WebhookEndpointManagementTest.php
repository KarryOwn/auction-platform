<?php

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;

test('user can view webhook management page and delivery history', function () {
    $user = createSeller();
    $endpoint = WebhookEndpoint::create([
        'user_id' => $user->id,
        'url' => 'https://example.com/hooks',
        'secret' => str_repeat('a', 64),
        'events' => ['bid.placed'],
        'is_active' => true,
    ]);
    WebhookDelivery::create([
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'bid.placed',
        'payload' => ['auction_id' => 1],
        'status' => 'delivered',
        'http_status' => 200,
        'attempt_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('user.webhooks.index'))
        ->assertOk()
        ->assertSeeText('Webhook Endpoints')
        ->assertSeeText('https://example.com/hooks')
        ->assertSeeText('Bid placed')
        ->assertSeeText('Delivered');
});

test('user can create and delete webhook endpoint from dashboard', function () {
    $user = createSeller();

    $response = $this->actingAs($user)->post(route('user.webhooks.store'), [
        'url' => 'https://integrator.example/webhooks/auction',
        'events' => ['bid.placed', 'auction.closed'],
    ]);

    $response->assertRedirect(route('user.webhooks.index'));

    $endpoint = WebhookEndpoint::query()->where('user_id', $user->id)->first();
    expect($endpoint)->not->toBeNull();
    expect($endpoint->events)->toBe(['bid.placed', 'auction.closed']);

    $this->actingAs($user)
        ->delete(route('user.webhooks.destroy', $endpoint))
        ->assertRedirect(route('user.webhooks.index'));

    expect(WebhookEndpoint::find($endpoint->id))->toBeNull();
});

test('webhook dashboard rejects localhost and private ip endpoints', function () {
    $user = createSeller();

    $this->actingAs($user)
        ->post(route('user.webhooks.store'), [
            'url' => 'http://localhost/webhook',
            'events' => ['bid.placed'],
        ])
        ->assertSessionHasErrors('url');

    $this->actingAs($user)
        ->post(route('user.webhooks.store'), [
            'url' => 'http://192.168.1.2/webhook',
            'events' => ['bid.placed'],
        ])
        ->assertSessionHasErrors('url');
});

test('user can send a webhook test delivery from dashboard', function () {
    Http::fake([
        'https://example.com/hooks' => Http::response(['ok' => true], 202),
    ]);

    $user = createSeller();
    $endpoint = WebhookEndpoint::create([
        'user_id' => $user->id,
        'url' => 'https://example.com/hooks',
        'secret' => str_repeat('e', 64),
        'events' => ['auction.closed'],
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post(route('user.webhooks.test', $endpoint));

    $response->assertRedirect(route('user.webhooks.index'));

    $delivery = WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint->id)->first();
    expect($delivery)->not->toBeNull();
    expect($delivery->event_type)->toBe('auction.closed');
    expect($delivery->status)->toBe('delivered');
    expect($delivery->payload['test'])->toBeTrue();
    expect($delivery->http_status)->toBe(202);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Webhook-Signature')
        && $request->hasHeader('X-Webhook-Delivery-Id')
        && $request->url() === 'https://example.com/hooks');
});
