<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $endpoints = WebhookEndpoint::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($endpoints);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:bid.placed,auction.closed,auction.cancelled'],
        ]);

        // SSRF protection
        $url = $validated['url'];
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (
                filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                throw ValidationException::withMessages([
                    'url' => ['Internal IP addresses are not allowed for webhooks.'],
                ]);
            }
        } elseif (in_array(strtolower($host), ['localhost', 'localhost.localdomain'])) {
            throw ValidationException::withMessages([
                'url' => ['Localhost is not allowed for webhooks.'],
            ]);
        }

        $endpoint = WebhookEndpoint::create([
            'user_id' => $request->user()->id,
            'url' => $url,
            'secret' => Str::random(64),
            'events' => array_unique($validated['events']),
            'is_active' => true,
        ]);

        return response()->json($endpoint, 201);
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        if ($endpoint->user_id !== $request->user()->id) {
            abort(403);
        }

        $endpoint->delete();

        return response()->json(null, 204);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $deliveries = WebhookDelivery::whereHas('webhookEndpoint', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })
            ->latest()
            ->paginate(15);

        return response()->json($deliveries);
    }

    public function redeliver(Request $request, WebhookDelivery $delivery, WebhookDispatchService $service): JsonResponse
    {
        if ($delivery->webhookEndpoint->user_id !== $request->user()->id) {
            abort(403);
        }

        $success = $service->deliver($delivery);

        return response()->json([
            'success' => $success,
            'delivery' => $delivery->fresh(),
        ]);
    }
}
