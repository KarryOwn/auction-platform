<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage Auctions</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Status Filter Tabs --}}
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.auctions.index') }}"
                   class="px-4 py-2 rounded-md text-sm font-medium {{ !request('status') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    All ({{ $statusCounts['all'] }})
                </a>
                @foreach(['active', 'completed', 'cancelled', 'draft'] as $s)
                    <a href="{{ route('admin.auctions.index', ['status' => $s]) }}"
                       class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === $s ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ ucfirst($s) }} ({{ $statusCounts[$s] }})
                    </a>
                @endforeach
            </div>

            {{-- Search --}}
            <div class="mb-4">
                <form method="GET" action="{{ route('admin.auctions.index') }}" class="flex gap-2">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search auctions by title..."
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Search</button>
                    @if(request('search'))
                        <a href="{{ route('admin.auctions.index', ['status' => request('status')]) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300">Clear</a>
                    @endif
                </form>
            </div>

            {{-- Auctions Table --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seller</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bids</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ends At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($auctions as $auction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{{ $auction->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('admin.auctions.show', $auction) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                        {{ Str::limit($auction->title, 40) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($auction->seller)
                                        <a href="{{ route('admin.users.show', $auction->seller->id) }}" class="text-indigo-600 hover:underline">
                                            {{ $auction->seller->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${{ number_format($auction->current_price, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $auction->bids_count ?? 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $colors = ['active' => 'bg-green-100 text-green-800', 'completed' => 'bg-blue-100 text-blue-800', 'cancelled' => 'bg-red-100 text-red-800', 'draft' => 'bg-gray-100 text-gray-800'];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colors[$auction->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($auction->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $auction->end_time->format('M d, Y H:i') }}
                                    @if($auction->status === 'active' && $auction->end_time->isFuture())
                                        <div class="text-xs text-orange-500">{{ $auction->end_time->diffForHumans() }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('admin.auctions.show', $auction) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">No auctions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $auctions->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
