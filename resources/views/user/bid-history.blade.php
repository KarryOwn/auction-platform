<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bid History') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Filters --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('user.bids') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">All</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="won" @selected(request('status') === 'won')>Won</option>
                            <option value="lost" @selected(request('status') === 'lost')>Lost</option>
                        </select>
                    </div>
                    <div>
                        <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                        <input type="date" name="from" id="from" value="{{ request('from') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <div>
                        <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                        <input type="date" name="to" id="to" value="{{ request('to') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                        Filter
                    </button>
                    @if(request()->hasAny(['status', 'from', 'to']))
                        <a href="{{ route('user.bids') }}" class="px-4 py-2 text-gray-600 text-sm hover:underline">Clear</a>
                    @endif
                </form>
            </div>

            {{-- Bid List --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                @if($bids->isEmpty())
                    <div class="p-12 text-center">
                        <p class="text-gray-400 text-lg">No bids found.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($bids as $bid)
                            <a href="{{ route('auctions.show', $bid->auction) }}" class="flex items-center gap-4 p-4 hover:bg-gray-50 transition">
                                <div class="w-16 h-16 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                                    @if($bid->auction->getCoverImageUrl('thumbnail'))
                                        <img src="{{ $bid->auction->getCoverImageUrl('thumbnail') }}" alt="" class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-900 truncate">{{ $bid->auction->title }}</div>
                                    <div class="text-sm text-gray-500">
                                        Bid: <span class="font-semibold">${{ number_format($bid->amount, 2) }}</span>
                                        · {{ $bid->created_at->format('M j, Y g:ia') }}
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    @php
                                        $statusColors = [
                                            'winning' => 'bg-green-100 text-green-800',
                                            'outbid'  => 'bg-red-100 text-red-800',
                                            'won'     => 'bg-green-100 text-green-800',
                                            'lost'    => 'bg-gray-100 text-gray-800',
                                            'cancelled' => 'bg-yellow-100 text-yellow-800',
                                            'ended'   => 'bg-gray-100 text-gray-600',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$bid->bid_status] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst($bid->bid_status) }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="p-4 border-t">
                        {{ $bids->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
