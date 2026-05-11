<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Export Requests</h2>
            <form method="GET" action="{{ route('admin.data-exports.index') }}" class="flex items-center gap-3">
                <label for="status" class="text-sm text-gray-600">Status</label>
                <select id="status" name="status" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="" @selected($status === '')>Pending</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="processing" @selected($status === 'processing')>Processing</option>
                    <option value="ready" @selected($status === 'ready')>Ready</option>
                    <option value="expired" @selected($status === 'expired')>Expired</option>
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                @if($exportRequests->isEmpty())
                    <div class="px-6 py-12 text-center text-sm text-gray-500">
                        No data export requests found for this status.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">User</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Requested</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Ready</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach($exportRequests as $exportRequest)
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-amber-100 text-amber-800',
                                            'processing' => 'bg-blue-100 text-blue-800',
                                            'ready' => 'bg-green-100 text-green-800',
                                            'expired' => 'bg-gray-100 text-gray-700',
                                        ];
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900">{{ $exportRequest->user?->name ?? 'Deleted user' }}</div>
                                            <div class="text-sm text-gray-500">{{ $exportRequest->user?->email ?? 'No email available' }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColors[$exportRequest->status] ?? 'bg-gray-100 text-gray-700' }}">
                                                {{ ucfirst($exportRequest->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">{{ $exportRequest->created_at?->format('M j, Y g:ia') }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-600">{{ $exportRequest->ready_at?->format('M j, Y g:ia') ?? 'Not ready' }}</td>
                                        <td class="px-6 py-4 text-right">
                                            @if($exportRequest->status === 'pending')
                                                <form method="POST" action="{{ route('admin.data-exports.approve', $exportRequest) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700">
                                                        Approve
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-sm text-gray-500">No action</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-100 px-6 py-4">
                        {{ $exportRequests->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
