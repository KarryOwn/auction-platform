<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebhookEndpointController extends Controller
{
    public function index(Request $request): View
    {
        $endpoints = WebhookEndpoint::query()
            ->where('user_id', $request->user()->id)
            ->withCount('deliveries')
            ->latest()
            ->get();

        $deliveries = WebhookDelivery::query()
            ->whereHas('webhookEndpoint', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('webhookEndpoint:id,url')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('event'), fn ($query) => $query->where('event_type', $request->string('event')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('user.webhooks.index', [
            'endpoints' => $endpoints,
            'deliveries' => $deliveries,
            'events' => $this->events(),
            'statuses' => ['pending', 'delivered', 'failed'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_keys($this->events()))],
        ]);

        $this->guardWebhookUrl($validated['url']);

        WebhookEndpoint::create([
            'user_id' => $request->user()->id,
            'url' => $validated['url'],
            'secret' => Str::random(64),
            'events' => array_values(array_unique($validated['events'])),
            'is_active' => true,
        ]);

        return redirect()
            ->route('user.webhooks.index')
            ->with('success', 'Webhook endpoint created.');
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        abort_unless((int) $endpoint->user_id === (int) $request->user()->id, 403);

        $endpoint->delete();

        return redirect()
            ->route('user.webhooks.index')
            ->with('success', 'Webhook endpoint deleted.');
    }

    public function testDelivery(Request $request, WebhookEndpoint $endpoint, WebhookDispatchService $service): RedirectResponse
    {
        abort_unless((int) $endpoint->user_id === (int) $request->user()->id, 403);

        $event = $endpoint->events[0] ?? 'bid.placed';

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => $event,
            'payload' => [
                'test' => true,
                'event' => $event,
                'message' => 'Test delivery from BidFlow webhook settings.',
                'sent_at' => now()->toIso8601String(),
            ],
            'status' => 'pending',
            'next_retry_at' => now(),
        ]);

        $success = $service->deliver($delivery);

        return redirect()
            ->route('user.webhooks.index')
            ->with($success ? 'success' : 'error', $success ? 'Test webhook delivered.' : 'Test webhook failed. Review the delivery history below.');
    }

    public function redeliver(Request $request, WebhookDelivery $delivery, WebhookDispatchService $service): RedirectResponse
    {
        abort_unless((int) $delivery->webhookEndpoint->user_id === (int) $request->user()->id, 403);

        $success = $service->deliver($delivery);

        return redirect()
            ->route('user.webhooks.index')
            ->with($success ? 'success' : 'error', $success ? 'Webhook redelivered.' : 'Webhook delivery failed again.');
    }

    /**
     * @return array<string, string>
     */
    protected function events(): array
    {
        return [
            'bid.placed' => 'Bid placed',
            'auction.closed' => 'Auction closed',
            'auction.cancelled' => 'Auction cancelled',
        ];
    }

    protected function guardWebhookUrl(string $url): void
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw ValidationException::withMessages([
                    'url' => ['Internal IP addresses are not allowed for webhooks.'],
                ]);
            }

            return;
        }

        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            throw ValidationException::withMessages([
                'url' => ['Localhost is not allowed for webhooks.'],
            ]);
        }
    }
}
