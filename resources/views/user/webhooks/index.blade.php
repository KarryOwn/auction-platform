<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-700">Developer settings</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-950">Webhook Endpoints</h2>
            </div>
            <a href="{{ route('user.api-tokens.index') }}" class="inline-flex rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300 hover:text-cyan-700">
                API tokens
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ $errors->first() }}</div>
            @endif

            <section class="grid gap-6 lg:grid-cols-[.95fr_1.05fr]">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Add endpoint</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-950">Send events to your application</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Use a public HTTPS URL. Private IPs and localhost endpoints are blocked server-side.</p>

                    <form method="POST" action="{{ route('user.webhooks.store') }}" class="mt-6 space-y-5">
                        @csrf
                        <div>
                            <label for="url" class="block text-sm font-semibold text-slate-700">Endpoint URL</label>
                            <input id="url" name="url" type="url" required value="{{ old('url') }}" placeholder="https://example.com/webhooks/auction-platform" class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                        </div>

                        <fieldset>
                            <legend class="text-sm font-semibold text-slate-700">Events</legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                @foreach($events as $event => $label)
                                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                        <input type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, old('events', ['bid.placed']), true)) class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white hover:bg-cyan-700">
                            Save endpoint
                        </button>
                    </form>
                </article>

                <article class="rounded-3xl border border-cyan-200 bg-cyan-50 p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-800">HMAC signing</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-950">Verify every delivery</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-700">Each endpoint receives a generated secret. We sign the timestamp plus JSON payload with SHA-256 and send these headers:</p>
                    <div class="mt-4 space-y-2 rounded-2xl bg-slate-950 p-4 font-mono text-xs text-cyan-100">
                        <p>X-Webhook-Event</p>
                        <p>X-Webhook-Timestamp</p>
                        <p>X-Webhook-Signature: t=...,v1=...</p>
                        <p>X-Webhook-Delivery-Id</p>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-cyan-900">Rotate secrets by deleting and recreating an endpoint. Test delivery UI is tracked separately in the platform phase.</p>
                </article>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Active endpoints</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-950">Registered destinations</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($endpoints as $endpoint)
                        <div class="grid gap-4 p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="break-all text-sm font-bold text-slate-950">{{ $endpoint->url }}</p>
                                    <span class="rounded-full {{ $endpoint->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }} px-2 py-1 text-xs font-semibold">
                                        {{ $endpoint->is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">
                                    Events: {{ collect($endpoint->events)->map(fn ($event) => $events[$event] ?? $event)->implode(', ') }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Last triggered: {{ $endpoint->last_triggered_at?->diffForHumans() ?? 'Never' }} · Deliveries: {{ $endpoint->deliveries_count }} · Failures: {{ $endpoint->failure_count }}
                                </p>
                                <p class="mt-2 font-mono text-xs text-slate-400">Secret prefix: {{ substr($endpoint->secret, 0, 8) }}...</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('user.webhooks.test', $endpoint) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex rounded-xl border border-cyan-200 px-4 py-2 text-sm font-semibold text-cyan-700 hover:bg-cyan-50">
                                        Send Test
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('user.webhooks.destroy', $endpoint) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="p-6 text-sm text-slate-500">No webhook endpoints yet. Add an endpoint to receive auction events.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Delivery history</p>
                            <h3 class="mt-2 text-xl font-bold text-slate-950">Recent webhook deliveries</h3>
                        </div>
                        <form method="GET" action="{{ route('user.webhooks.index') }}" class="grid gap-3 sm:grid-cols-3">
                            <select name="status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                                <option value="">All statuses</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            <select name="event" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                                <option value="">All events</option>
                                @foreach($events as $event => $label)
                                    <option value="{{ $event }}" @selected(request('event') === $event)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700">Filter</button>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Event</th>
                                <th class="px-6 py-3">Endpoint</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">HTTP</th>
                                <th class="px-6 py-3">Attempts</th>
                                <th class="px-6 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($deliveries as $delivery)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-slate-900">{{ $events[$delivery->event_type] ?? $delivery->event_type }}</td>
                                    <td class="max-w-xs truncate px-6 py-4 text-slate-500">{{ $delivery->webhookEndpoint->url }}</td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $delivery->status === 'delivered' ? 'bg-emerald-100 text-emerald-700' : ($delivery->status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                            {{ ucfirst($delivery->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500">{{ $delivery->http_status ?? '-' }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $delivery->attempt_count }}</td>
                                    <td class="px-6 py-4">
                                        @if($delivery->status === 'failed')
                                            <form method="POST" action="{{ route('user.webhooks.redeliver', $delivery) }}">
                                                @csrf
                                                <button class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Redeliver</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-400">{{ $delivery->created_at?->diffForHumans() }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No deliveries yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($deliveries->hasPages())
                    <div class="border-t border-slate-100 p-6">
                        {{ $deliveries->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
