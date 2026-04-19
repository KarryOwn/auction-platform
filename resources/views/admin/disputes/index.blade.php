<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dispute Queue</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.disputes.index') }}"
                   class="px-4 py-2 rounded-md text-sm font-medium {{ !request('status') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    All ({{ $statusCounts['all'] }})
                </a>
                @foreach(['open', 'under_review', 'resolved_buyer', 'resolved_seller', 'closed'] as $status)
                    <a href="{{ route('admin.disputes.index', ['status' => $status, 'type' => request('type')]) }}"
                       class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === $status ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ ucfirst(str_replace('_', ' ', $status)) }} ({{ $statusCounts[$status] }})
                    </a>
                @endforeach
            </div>

            <div class="mb-4 bg-white p-4 rounded-lg shadow-sm">
                <form method="GET" action="{{ route('admin.disputes.index') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    <div>
                        <label for="type" class="block text-sm text-gray-600 mb-1">Type</label>
                        <select id="type" name="type" class="rounded-md border-gray-300 text-sm">
                            <option value="">All types</option>
                            <option value="item_not_received" @selected($selectedType === 'item_not_received')>Item not received</option>
                            <option value="not_as_described" @selected($selectedType === 'not_as_described')>Not as described</option>
                            <option value="non_payment" @selected($selectedType === 'non_payment')>Non payment</option>
                            <option value="other" @selected($selectedType === 'other')>Other</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Apply</button>
                        <a href="{{ route('admin.disputes.index', ['status' => request('status')]) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300">Clear</a>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Auction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Claimant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Respondent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($disputes as $dispute)
                            @php
                                $statusClasses = [
                                    'open' => 'bg-red-100 text-red-800',
                                    'under_review' => 'bg-amber-100 text-amber-800',
                                    'resolved_buyer' => 'bg-blue-100 text-blue-800',
                                    'resolved_seller' => 'bg-green-100 text-green-800',
                                    'closed' => 'bg-gray-100 text-gray-800',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{{ $dispute->id }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    @if($dispute->auction)
                                        <a href="{{ route('admin.auctions.show', $dispute->auction) }}" class="text-indigo-600 hover:underline">
                                            {{ \Illuminate\Support\Str::limit($dispute->auction->title, 36) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">Deleted auction</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $dispute->claimant?->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $dispute->respondent?->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $dispute->type)) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClasses[$dispute->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $dispute->status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $dispute->created_at->format('M d, Y H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('admin.disputes.show', $dispute) }}" class="text-indigo-600 hover:text-indigo-900">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">No disputes found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $disputes->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
