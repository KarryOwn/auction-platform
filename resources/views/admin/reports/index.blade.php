<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reported Auctions</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Status Filter Tabs --}}
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.reports.index') }}"
                   class="px-4 py-2 rounded-md text-sm font-medium {{ !request('status') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    All ({{ $statusCounts['all'] }})
                </a>
                @foreach(['pending', 'reviewed', 'actioned', 'dismissed'] as $s)
                    @php
                        $tabColors = ['pending' => 'bg-yellow-600', 'reviewed' => 'bg-blue-600', 'actioned' => 'bg-green-600', 'dismissed' => 'bg-gray-600'];
                    @endphp
                    <a href="{{ route('admin.reports.index', ['status' => $s]) }}"
                       class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === $s ? ($tabColors[$s] ?? 'bg-indigo-600') . ' text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ ucfirst($s) }} ({{ $statusCounts[$s] }})
                    </a>
                @endforeach
            </div>

            {{-- Reports Table --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Auction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporter</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reviewer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($reports as $report)
                            <tr class="hover:bg-gray-50" id="report-row-{{ $report->id }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{{ $report->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($report->auction)
                                        <a href="{{ route('admin.auctions.show', $report->auction_id) }}" class="text-indigo-600 hover:underline">
                                            {{ Str::limit($report->auction->title, 30) }}
                                        </a>
                                        @php
                                            $aColors = ['active' => 'text-green-600', 'completed' => 'text-blue-600', 'cancelled' => 'text-red-600'];
                                        @endphp
                                        <span class="text-xs {{ $aColors[$report->auction->status] ?? 'text-gray-500' }} ml-1">({{ $report->auction->status }})</span>
                                    @else
                                        <span class="text-gray-400">Deleted</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($report->reporter)
                                        <a href="{{ route('admin.users.show', $report->reporter->id) }}" class="text-indigo-600 hover:underline">
                                            {{ $report->reporter->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">Deleted</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="font-medium">{{ $report->reason }}</div>
                                    @if($report->description)
                                        <div class="text-xs text-gray-500 mt-1">{{ Str::limit($report->description, 60) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'pending'   => 'bg-yellow-100 text-yellow-800',
                                            'reviewed'  => 'bg-blue-100 text-blue-800',
                                            'actioned'  => 'bg-green-100 text-green-800',
                                            'dismissed' => 'bg-gray-100 text-gray-800',
                                        ];
                                    @endphp
                                    <span id="report-status-{{ $report->id }}"
                                          class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$report->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($report->status) }}
                                    </span>
                                    @if($report->admin_notes)
                                        <div class="text-xs text-gray-500 mt-1" title="{{ $report->admin_notes }}">{{ Str::limit($report->admin_notes, 30) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $report->reviewer?->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $report->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($report->isPending())
                                        <div class="flex flex-col gap-1">
                                            <button onclick="reviewReport({{ $report->id }}, 'reviewed')"
                                                    class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                                Reviewed
                                            </button>
                                            <button onclick="reviewReport({{ $report->id }}, 'actioned')"
                                                    class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200">
                                                Action
                                            </button>
                                            <button onclick="reviewReport({{ $report->id }}, 'dismissed')"
                                                    class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                                Dismiss
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">Resolved</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">No reports found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $reports->links() }}
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        async function reviewReport(reportId, status) {
            const notes = prompt(`Admin notes for marking as "${status}" (optional):`);
            if (notes === null) return; // cancelled

            try {
                const resp = await fetch(`/admin/reports/${reportId}/review`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status, admin_notes: notes || null }),
                });
                const data = await resp.json();

                if (resp.ok) {
                    // Update the row in place
                    const statusEl = document.getElementById(`report-status-${reportId}`);
                    const statusColors = {
                        reviewed: 'bg-blue-100 text-blue-800',
                        actioned: 'bg-green-100 text-green-800',
                        dismissed: 'bg-gray-100 text-gray-800',
                    };
                    statusEl.className = `px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusColors[status]}`;
                    statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);

                    // Remove action buttons
                    const row = document.getElementById(`report-row-${reportId}`);
                    const actionsCell = row.querySelector('td:last-child');
                    actionsCell.innerHTML = '<span class="text-xs text-gray-400">Resolved</span>';

                    alert(data.message);
                } else {
                    alert(data.message || 'Failed to review report.');
                }
            } catch {
                alert('Request failed.');
            }
        }
    </script>
    @endpush
</x-app-layout>
