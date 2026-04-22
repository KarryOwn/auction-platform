<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Auctions</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('seller.auctions.schedule') }}" class="inline-flex items-center px-4 py-2 border border-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                    Schedule View
                </a>
                <a href="{{ route('seller.auctions.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                    Create New Auction
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('status'))
                <div class="bg-green-100 text-green-800 px-4 py-3 rounded">{{ session('status') }}</div>
            @endif

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                Re-listing creates a new draft from a completed or cancelled auction. Review dates, quantity, shipping, and pricing before publishing the new listing.
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                @php
                    $tabs = [
                        'all' => 'All',
                        \App\Models\Auction::STATUS_ACTIVE => 'Active',
                        \App\Models\Auction::STATUS_DRAFT => 'Draft',
                        \App\Models\Auction::STATUS_COMPLETED => 'Completed',
                        \App\Models\Auction::STATUS_CANCELLED => 'Cancelled',
                    ];
                @endphp
                <div class="flex flex-wrap gap-2">
                    @foreach($tabs as $key => $label)
                        <a href="{{ route('seller.auctions.index', $key === 'all' ? [] : ['status' => $key]) }}"
                           class="px-3 py-1.5 rounded-full text-sm {{ ($status === '' && $key === 'all') || $status === $key ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $label }} ({{ $counts[$key] ?? 0 }})
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-3">Auction</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Current Price</th>
                            <th class="text-left px-4 py-3">Bids</th>
                            <th class="text-left px-4 py-3">Time</th>
                            <th class="text-left px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($auctions as $auction)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($auction->getCoverImageUrl())
                                            <img src="{{ $auction->getCoverImageUrl() }}" class="w-14 h-14 object-cover rounded" alt="{{ $auction->title }}">
                                        @else
                                            <div class="w-14 h-14 rounded bg-gray-100"></div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $auction->title }}</div>
                                            <div class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($auction->description, 60) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($auction->paused_by_vacation)
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Paused (Vacation)</span>
                                    @else
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ ucfirst($auction->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">${{ number_format($auction->current_price, 2) }}</td>
                                <td class="px-4 py-3">{{ $auction->bids_count ?? $auction->bid_count }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if($auction->status === \App\Models\Auction::STATUS_ACTIVE)
                                        {{ $auction->timeRemaining() }}
                                    @else
                                        {{ optional($auction->closed_at ?? $auction->end_time)->diffForHumans() }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('seller.auctions.edit', $auction) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                                        @if($auction->status === \App\Models\Auction::STATUS_ACTIVE)
                                            <a href="{{ route('seller.auctions.insights', $auction) }}" class="text-emerald-600 hover:text-emerald-800">Insights</a>
                                        @endif
                                        <a href="{{ route('auctions.show', $auction) }}" class="text-gray-600 hover:text-gray-800">View</a>
                                        @if($auction->isDraft())
                                            <form method="POST" action="{{ route('seller.auctions.destroy', $auction) }}" onsubmit="return confirm('Delete this draft?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-600 hover:text-red-800">Delete</button>
                                            </form>
                                        @elseif($auction->status === \App\Models\Auction::STATUS_ACTIVE && (int) $auction->bid_count === 0)
                                            <form method="POST" action="{{ route('seller.auctions.cancel', $auction) }}" onsubmit="return confirm('Cancel this auction?')">
                                                @csrf
                                                <button class="text-red-600 hover:text-red-800">Cancel</button>
                                            </form>
                                        @elseif(in_array($auction->status, [\App\Models\Auction::STATUS_COMPLETED, \App\Models\Auction::STATUS_CANCELLED], true))
                                            <form method="POST" action="{{ route('seller.auctions.clone', $auction) }}" onsubmit="return confirm('Create a new draft from this auction? Review dates and pricing before publishing.')">
                                                @csrf
                                                <button class="text-amber-700 hover:text-amber-900 font-medium">Re-list</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No auctions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $auctions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
