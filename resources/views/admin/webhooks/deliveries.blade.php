<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Platform operations</p>
                <h2 class="text-xl font-semibold text-gray-800 leading-tight">Webhook Delivery Log</h2>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="inline-flex rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                Admin Monitor
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <form method="GET" action="{{ route('admin.webhook-deliveries.index') }}" class="grid gap-4 md:grid-cols-4 md:items-end">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All statuses</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="event" class="block text-sm font-medium text-gray-700">Event</label>
                        <select id="event" name="event" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All events</option>
                            @foreach($events as $event => $label)
                                <option value="{{ $event }}" @selected(request('event') === $event)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="user" class="block text-sm font-medium text-gray-700">Owner</label>
                        <input id="user" name="user" value="{{ request('user') }}" placeholder="Name, email, or ID" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
                        <a href="{{ route('admin.webhook-deliveries.index') }}" class="inline-flex rounded-md border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Reset</a>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                @if($deliveries->isEmpty())
                    <div class="px-6 py-12 text-center text-sm text-gray-500">No webhook deliveries found.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">ID</th>
                                    <th class="px-6 py-3">Owner</th>
                                    <th class="px-6 py-3">Endpoint</th>
                                    <th class="px-6 py-3">Event</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">HTTP</th>
                                    <th class="px-6 py-3">Attempts</th>
                                    <th class="px-6 py-3">Next Retry</th>
                                    <th class="px-6 py-3">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($deliveries as $delivery)
                                    <tr>
                                        <td class="px-6 py-4 text-gray-400">#{{ $delivery->id }}</td>
                                        <td class="px-6 py-4">
                                            <p class="font-medium text-gray-900">{{ $delivery->webhookEndpoint->user?->name ?? 'Platform endpoint' }}</p>
                                            <p class="text-xs text-gray-500">{{ $delivery->webhookEndpoint->user?->email ?? 'No owner' }}</p>
                                        </td>
                                        <td class="max-w-xs truncate px-6 py-4 text-gray-600">{{ $delivery->webhookEndpoint->url }}</td>
                                        <td class="px-6 py-4 font-medium text-gray-900">{{ $events[$delivery->event_type] ?? $delivery->event_type }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $delivery->status === 'delivered' ? 'bg-green-100 text-green-800' : ($delivery->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                                                {{ ucfirst($delivery->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">{{ $delivery->http_status ?? '-' }}</td>
                                        <td class="px-6 py-4 text-gray-600">{{ $delivery->attempt_count }}</td>
                                        <td class="px-6 py-4 text-gray-600">{{ $delivery->next_retry_at?->format('M j, g:ia') ?? '-' }}</td>
                                        <td class="px-6 py-4 text-gray-500">{{ $delivery->created_at?->format('M j, Y g:ia') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-100 p-4">
                        {{ $deliveries->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
