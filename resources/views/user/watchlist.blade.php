<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Watchlist') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Sort Controls --}}
            <div class="flex items-center justify-between mb-6">
                <p class="text-sm text-gray-600">{{ $active->count() }} active · {{ $ended->count() }} ended</p>
                <div class="flex gap-2">
                    <a href="{{ route('user.watchlist', ['sort' => 'ending_soon']) }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium {{ $sort === 'ending_soon' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} transition">
                        Ending Soon
                    </a>
                    <a href="{{ route('user.watchlist', ['sort' => 'recently_added']) }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium {{ $sort === 'recently_added' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} transition">
                        Recently Added
                    </a>
                </div>
            </div>

            {{-- Active Items --}}
            @if($active->isEmpty() && $ended->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                    <p class="text-gray-400 text-lg">Your watchlist is empty.</p>
                    <a href="{{ route('auctions.index') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                        Browse Auctions
                    </a>
                </div>
            @else
                @if($active->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($active as $watcher)
                            @php $auction = $watcher->auction; @endphp
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200">
                                <div class="aspect-video bg-gray-100">
                                    @if($auction->getCoverImageUrl('gallery'))
                                        <img src="{{ $auction->getCoverImageUrl('gallery') }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">No image</div>
                                    @endif
                                </div>
                                <div class="p-5">
                                    <h3 class="font-bold text-gray-900 truncate">{{ $auction->title }}</h3>
                                    <div class="flex items-end justify-between mt-3">
                                        <div>
                                            <span class="text-green-600 font-bold text-xl">${{ number_format($auction->current_price, 2) }}</span>
                                            <span class="block text-xs text-gray-400 mt-0.5">{{ $auction->bid_count }} bids</span>
                                        </div>
                                        <a href="{{ route('auctions.show', $auction) }}"
                                           class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">
                                            Bid Now
                                        </a>
                                    </div>
                                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                        <span class="text-xs text-orange-600 font-medium">{{ $auction->timeRemaining() }}</span>
                                        <form method="POST" action="{{ route('auctions.watch', $auction) }}">
                                            @csrf
                                            <button type="submit" class="text-red-400 hover:text-red-600 transition" title="Remove from watchlist">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Ended Items --}}
                @if($ended->isNotEmpty())
                    <div class="mt-10">
                        <h3 class="text-lg font-semibold text-gray-600 mb-4">Ended Auctions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 opacity-60">
                            @foreach($ended as $watcher)
                                @php $auction = $watcher->auction; @endphp
                                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                    <div class="aspect-video bg-gray-100">
                                        @if($auction->getCoverImageUrl('gallery'))
                                            <img src="{{ $auction->getCoverImageUrl('gallery') }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <div class="p-5">
                                        <h3 class="font-bold text-gray-700 truncate">{{ $auction->title }}</h3>
                                        <div class="mt-2 text-sm text-gray-500">Final: ${{ number_format($auction->current_price, 2) }}</div>
                                        <span class="mt-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Ended</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
