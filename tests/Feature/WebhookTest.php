<?php

namespace Tests\Feature;

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_webhook_endpoint_with_ssrf_protection()
    {
        $user = User::factory()->create();

        // Valid external URL
        $response = $this->actingAs($user)->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['bid.placed'],
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('webhook_endpoints', ['url' => 'https://example.com/webhook']);

        // Invalid internal URL (localhost)
        $response = $this->actingAs($user)->postJson('/api/v1/webhooks', [
            'url' => 'http://localhost/webhook',
            'events' => ['bid.placed'],
        ]);
        
        $response->assertStatus(422)->assertJsonStructure(['details' => ['url']]);

        // Invalid internal IP
        $response = $this->actingAs($user)->postJson('/api/v1/webhooks', [
            'url' => 'http://192.168.1.1/webhook',
            'events' => ['bid.placed'],
        ]);
        $response->assertStatus(422)->assertJsonStructure(['details' => ['url']]);
    }

    public function test_bid_placed_event_triggers_webhook_dispatch()
    {
        Queue::fake([\App\Jobs\DeliverWebhook::class]);

        $seller = User::factory()->create();
        $bidder = User::factory()->create();
        $auction = Auction::factory()->create(['user_id' => $seller->id]);
        $bid = Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $bidder->id, 'amount' => 100]);

        $endpoint = WebhookEndpoint::create([
            'user_id' => $seller->id,
            'url' => 'https://example.com/hook',
            'secret' => 'testsecret',
            'events' => ['bid.placed'],
            'is_active' => true,
        ]);

        $event = new BidPlaced($bid, $auction);
        
        $listener = new \App\Listeners\DispatchWebhooksListener(app(WebhookDispatchService::class));
        $listener->handleBidPlaced($event);

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'bid.placed',
            'status' => 'pending',
        ]);

        Queue::assertPushed(\App\Jobs\DeliverWebhook::class);
    }

    public function test_webhook_delivery_success_and_signature()
    {
        Http::fake([
            'https://example.com/hook' => Http::response('OK', 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'user_id' => User::factory()->create()->id,
            'url' => 'https://example.com/hook',
            'secret' => 'testsecret',
            'events' => ['bid.placed'],
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'bid.placed',
            'payload' => ['foo' => 'bar'],
            'status' => 'pending',
        ]);

        $service = new WebhookDispatchService();
        $success = $service->deliver($delivery);

        $this->assertTrue($success);
        $this->assertEquals('delivered', $delivery->fresh()->status);
        $this->assertEquals(200, $delivery->fresh()->http_status);
        
        $endpoint->refresh();
        $this->assertEquals(0, $endpoint->failure_count);
        $this->assertNotNull($endpoint->last_triggered_at);

        Http::assertSent(function ($request) use ($endpoint) {
            $signatureHeader = $request->header('X-Webhook-Signature')[0];
            $timestampHeader = $request->header('X-Webhook-Timestamp')[0];
            
            // Re-verify signature logic
            $payload = json_encode(['foo' => 'bar']);
            $expectedSig = hash_hmac('sha256', "{$timestampHeader}.{$payload}", $endpoint->secret);
            
            return str_contains($signatureHeader, "v1={$expectedSig}") &&
                   $request->url() === 'https://example.com/hook';
        });
    }

    public function test_webhook_delivery_failure_and_retry_logic()
    {
        Http::fake([
            'https://example.com/hook' => Http::response('Error', 500),
        ]);

        $endpoint = WebhookEndpoint::create([
            'user_id' => User::factory()->create()->id,
            'url' => 'https://example.com/hook',
            'secret' => 'testsecret',
            'events' => ['bid.placed'],
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'bid.placed',
            'payload' => [],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $service = new WebhookDispatchService();
        $success = $service->deliver($delivery);

        $this->assertFalse($success);
        
        $delivery->refresh();
        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->next_retry_at); // Should be set

        $endpoint->refresh();
        $this->assertEquals(1, $endpoint->failure_count);
    }

    public function test_endpoint_disabled_after_10_failures()
    {
        Http::fake([
            'https://example.com/hook' => Http::response('Error', 500),
        ]);

        $endpoint = WebhookEndpoint::create([
            'user_id' => User::factory()->create()->id,
            'url' => 'https://example.com/hook',
            'secret' => 'testsecret',
            'events' => ['bid.placed'],
            'is_active' => true,
            'failure_count' => 9, // One more failure to trigger disable
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'bid.placed',
            'payload' => [],
            'status' => 'pending',
        ]);

        $service = new WebhookDispatchService();
        $service->deliver($delivery);

        $endpoint->refresh();
        $this->assertEquals(10, $endpoint->failure_count);
        $this->assertFalse($endpoint->is_active);
    }
}
