<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Live Auctions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($auctions as $auction)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200">
                        <div class="aspect-video bg-gray-100">
                            @if($auction->getCoverImageUrl('gallery'))
                                <img src="{{ $auction->getCoverImageUrl('gallery') }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">No image</div>
                            @endif
                        </div>
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="font-bold text-lg text-gray-900 line-clamp-1">{{ $auction->title }}</h3>
                                @if($auction->is_featured)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 flex-shrink-0 ml-2">
                                        ★ Featured
                                    </span>
                                @endif
                            </div>

                            <p class="text-gray-600 text-sm mb-4 line-clamp-2">{{ $auction->description }}</p>

                            {{-- Reserve Indicator --}}
                            @if($auction->hasReserve())
                                <div class="mb-3">
                                    @if($auction->reserve_met)
                                        <span class="inline-flex items-center text-xs text-green-600 font-medium">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Reserve met
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-xs text-orange-600 font-medium">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"/></svg>
                                            Reserve not met
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div class="flex items-end justify-between mt-auto">
                                <div>
                                    <span class="text-green-600 font-bold text-2xl">${{ number_format($auction->current_price, 2) }}</span>
                                    <span class="block text-xs text-gray-400 mt-0.5">
                                        {{ $auction->bids_count ?? $auction->bid_count ?? 0 }} bid{{ ($auction->bids_count ?? $auction->bid_count ?? 0) !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                                <a href="{{ route('auctions.show', $auction) }}"
                                   class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">
                                    Bid Now
                                </a>
                            </div>

                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                <span class="text-xs text-gray-400">
                                    Ends {{ $auction->end_time->diffForHumans() }}
                                </span>
                                @if($auction->extension_count > 0)
                                    <span class="text-xs text-orange-500 font-medium">
                                        Extended {{ $auction->extension_count }}×
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($auctions->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                    <p class="text-gray-400 text-lg">No live auctions right now.</p>
                    <p class="text-gray-400 text-sm mt-1">Check back soon!</p>
                </div>
            @endif

            <div class="mt-6">
                {{ $auctions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>