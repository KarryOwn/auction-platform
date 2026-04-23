<?php

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

test('staff can view platform webhook delivery log', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = createSeller(['name' => 'Integration Seller', 'email' => 'seller@example.test']);
    $endpoint = WebhookEndpoint::create([
        'user_id' => $owner->id,
        'url' => 'https://example.com/webhooks',
        'secret' => str_repeat('b', 64),
        'events' => ['auction.closed'],
        'is_active' => true,
    ]);
    WebhookDelivery::create([
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'auction.closed',
        'payload' => ['auction_id' => 10],
        'status' => 'failed',
        'http_status' => 500,
        'attempt_count' => 3,
        'next_retry_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.webhook-deliveries.index'))
        ->assertOk()
        ->assertSeeText('Webhook Delivery Log')
        ->assertSeeText('Integration Seller')
        ->assertSeeText('https://example.com/webhooks')
        ->assertSeeText('Auction closed')
        ->assertSeeText('Failed')
        ->assertSeeText('500');
});

test('admin webhook delivery log can filter by status event and owner', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $matchingOwner = createSeller(['name' => 'Filtered Seller']);
    $otherOwner = createSeller(['name' => 'Other Seller']);

    $matchingEndpoint = WebhookEndpoint::create([
        'user_id' => $matchingOwner->id,
        'url' => 'https://match.example/hooks',
        'secret' => str_repeat('c', 64),
        'events' => ['bid.placed'],
        'is_active' => true,
    ]);
    $otherEndpoint = WebhookEndpoint::create([
        'user_id' => $otherOwner->id,
        'url' => 'https://other.example/hooks',
        'secret' => str_repeat('d', 64),
        'events' => ['auction.cancelled'],
        'is_active' => true,
    ]);

    WebhookDelivery::create([
        'webhook_endpoint_id' => $matchingEndpoint->id,
        'event_type' => 'bid.placed',
        'payload' => ['bid_id' => 1],
        'status' => 'delivered',
        'http_status' => 200,
    ]);
    WebhookDelivery::create([
        'webhook_endpoint_id' => $otherEndpoint->id,
        'event_type' => 'auction.cancelled',
        'payload' => ['auction_id' => 2],
        'status' => 'failed',
        'http_status' => 503,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.webhook-deliveries.index', [
            'status' => 'delivered',
            'event' => 'bid.placed',
            'user' => 'Filtered',
        ]))
        ->assertOk()
        ->assertSeeText('Filtered Seller')
        ->assertSeeText('https://match.example/hooks')
        ->assertDontSeeText('Other Seller')
        ->assertDontSeeText('https://other.example/hooks');
});
