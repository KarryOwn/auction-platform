<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Jobs\ProcessCoalescedBidWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebhookDispatchService
{
    /**
     * Queue delivery of an event to all matching endpoints.
     */
    public function dispatch(string $eventType, array $payload, ?int $userId = null): void
    {
        foreach ($this->matchingEndpoints($eventType, $userId) as $endpoint) {
            $this->queueDelivery($endpoint, $eventType, $payload);
        }
    }

    public function dispatchCoalescedBidPlaced(array $payload, ?int $userId = null): void
    {
        if (! $userId || empty($payload['auction_id'])) {
            $this->dispatch('bid.placed', $payload, $userId);

            return;
        }

        $auctionId = (int) $payload['auction_id'];
        $stateKey = $this->bidWebhookStateKey($userId, $auctionId);
        $scheduleKey = $this->bidWebhookScheduleKey($userId, $auctionId);
        $nowMs = (int) floor(microtime(true) * 1000);

        try {
            $existing = Redis::get($stateKey);
            $state = $existing ? json_decode((string) $existing, true) : [];

            if (! is_array($state)) {
                $state = [];
            }

            $firstAtMs = (int) ($state['first_event_at_ms'] ?? $nowMs);
            $previousDelta = (int) ($state['bid_count_delta'] ?? 0);

            Redis::setex($stateKey, 60, json_encode([
                'auction_id' => $auctionId,
                'current_price' => (float) ($payload['current_price'] ?? $payload['amount'] ?? 0),
                'latest_bid_id' => (int) ($payload['bid_id'] ?? 0),
                'latest_bidder_id' => (int) ($payload['bidder_id'] ?? 0),
                'bid_count' => (int) ($payload['bid_count'] ?? 0),
                'bid_count_delta' => $previousDelta + 1,
                'first_event_at_ms' => $firstAtMs,
                'last_event_at_ms' => $nowMs,
            ], JSON_THROW_ON_ERROR));

            $windowMs = max(250, (int) config('auction.webhooks.bid_placed_coalesce_window_ms', 1000));
            $scheduled = Redis::set($scheduleKey, '1', 'PX', $windowMs, 'NX');

            if ($scheduled) {
                ProcessCoalescedBidWebhook::dispatch($userId, $auctionId)
                    ->delay(now()->addSeconds(max(1, (int) ceil($windowMs / 1000))))
                    ->onConnection((string) config('auction.webhooks_queue.connection', 'redis'))
                    ->onQueue('webhooks');
            }
        } catch (\Throwable $e) {
            Log::warning('WebhookDispatchService: coalesced bid webhook failed open', [
                'auction_id' => $auctionId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('bid.placed', $payload, $userId);
        }
    }

    public function flushCoalescedBidPlaced(int $userId, int $auctionId): void
    {
        $stateKey = $this->bidWebhookStateKey($userId, $auctionId);
        $scheduleKey = $this->bidWebhookScheduleKey($userId, $auctionId);
        $stateJson = Redis::get($stateKey);

        Redis::del([$stateKey, $scheduleKey]);

        if (! $stateJson) {
            return;
        }

        $state = json_decode((string) $stateJson, true);

        if (! is_array($state)) {
            return;
        }

        $firstAtMs = (int) ($state['first_event_at_ms'] ?? 0);
        $lastAtMs = (int) ($state['last_event_at_ms'] ?? $firstAtMs);
        $payload = [
            'auction_id' => $auctionId,
            'current_price' => (float) ($state['current_price'] ?? 0),
            'latest_bid_id' => (int) ($state['latest_bid_id'] ?? 0),
            'latest_bidder_id' => (int) ($state['latest_bidder_id'] ?? 0),
            'bid_count' => (int) ($state['bid_count'] ?? 0),
            'bid_count_delta' => (int) ($state['bid_count_delta'] ?? 0),
            'event_window_ms' => max(0, $lastAtMs - $firstAtMs),
        ];

        foreach ($this->matchingEndpoints('bid.placed', $userId) as $endpoint) {
            $this->queueDelivery($endpoint, 'bid.placed.batch', $payload);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, WebhookEndpoint>
     */
    private function matchingEndpoints(string $eventType, ?int $userId = null)
    {
        return WebhookEndpoint::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id');         // platform-wide
                if ($userId) {
                    $q->orWhere('user_id', $userId); // user-specific
                }
            })
            ->whereJsonContains('events', $eventType)
            ->get();
    }

    private function queueDelivery(WebhookEndpoint $endpoint, string $eventType, array $payload): void
    {
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type'          => $eventType,
            'payload'             => $payload,
            'status'              => 'pending',
            'next_retry_at'       => now(),
        ]);

        DeliverWebhook::dispatch($delivery->id)
            ->onConnection((string) config('auction.webhooks_queue.connection', 'redis'))
            ->onQueue('webhooks');
    }

    /**
     * Deliver a single webhook delivery with HMAC signing.
     */
    public function deliver(WebhookDelivery $delivery): bool
    {
        $endpoint = $delivery->webhookEndpoint;
        $payload  = json_encode($delivery->payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'          => 'application/json',
                    'X-Webhook-Event'       => $delivery->event_type,
                    'X-Webhook-Timestamp'   => $timestamp,
                    'X-Webhook-Signature'   => "t={$timestamp},v1={$signature}",
                    'X-Webhook-Delivery-Id' => $delivery->id,
                ])
                ->post($endpoint->url, $delivery->payload);

            $success = $response->status() >= 200 && $response->status() < 300;

            $delivery->update([
                'status'        => $success ? 'delivered' : 'failed',
                'http_status'   => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'attempt_count' => $delivery->attempt_count + 1,
                'next_retry_at' => $success ? null : $this->nextRetryAt($delivery->attempt_count + 1),
            ]);

            if ($success) {
                $endpoint->update([
                    'last_triggered_at' => now(),
                    'failure_count'     => 0,
                ]);
            } else {
                $endpoint->increment('failure_count');
                // Disable after 10 consecutive failures
                if ($endpoint->failure_count >= 10) {
                    $endpoint->update(['is_active' => false]);
                    Log::warning('WebhookDispatchService: endpoint disabled after failures', [
                        'endpoint_id' => $endpoint->id,
                        'url'         => $endpoint->url,
                    ]);
                }
            }

            return $success;

        } catch (\Throwable $e) {
            $delivery->update([
                'status'        => 'failed',
                'attempt_count' => $delivery->attempt_count + 1,
                'next_retry_at' => $this->nextRetryAt($delivery->attempt_count + 1),
                'response_body' => substr($e->getMessage(), 0, 1000),
            ]);
            return false;
        }
    }

    private function nextRetryAt(int $attempt): \Carbon\Carbon
    {
        // Exponential backoff: 1m, 5m, 30m, 2h, 8h
        $delayMinutes = [1, 5, 30, 120, 480][$attempt - 1] ?? 480;
        return now()->addMinutes($delayMinutes);
    }

    private function bidWebhookStateKey(int $userId, int $auctionId): string
    {
        return "webhooks:bid_placed:state:{$userId}:{$auctionId}";
    }

    private function bidWebhookScheduleKey(int $userId, int $auctionId): string
    {
        return "webhooks:bid_placed:scheduled:{$userId}:{$auctionId}";
    }
}
