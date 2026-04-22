<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatchService
{
    /**
     * Queue delivery of an event to all matching endpoints.
     */
    public function dispatch(string $eventType, array $payload, ?int $userId = null): void
    {
        $endpoints = WebhookEndpoint::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id');         // platform-wide
                if ($userId) {
                    $q->orWhere('user_id', $userId); // user-specific
                }
            })
            ->whereJsonContains('events', $eventType)
            ->get();

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event_type'          => $eventType,
                'payload'             => $payload,
                'status'              => 'pending',
                'next_retry_at'       => now(),
            ]);

            \App\Jobs\DeliverWebhook::dispatch($delivery->id)->onQueue('default');
        }
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
}
