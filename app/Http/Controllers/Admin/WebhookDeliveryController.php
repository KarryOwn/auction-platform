<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $deliveries = WebhookDelivery::query()
            ->with(['webhookEndpoint:id,user_id,url', 'webhookEndpoint.user:id,name,email'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('event'), fn ($query) => $query->where('event_type', $request->string('event')))
            ->when($request->filled('user'), function ($query) use ($request) {
                $term = $request->string('user')->toString();

                $query->whereHas('webhookEndpoint.user', function ($userQuery) use ($term) {
                    $userQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");

                    if (ctype_digit($term)) {
                        $userQuery->orWhereKey((int) $term);
                    }
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.webhooks.deliveries', [
            'deliveries' => $deliveries,
            'statuses' => ['pending', 'delivered', 'failed'],
            'events' => [
                'bid.placed' => 'Bid placed',
                'auction.closed' => 'Auction closed',
                'auction.cancelled' => 'Auction cancelled',
            ],
        ]);
    }
}
