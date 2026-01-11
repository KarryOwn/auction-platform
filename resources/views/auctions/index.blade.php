<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Live Auctions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($auctions as $auction)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-bold text-lg mb-2">{{ $auction->title }}</h3>
                        <p class="text-gray-600 mb-4">{{ Str::limit($auction->description, 100) }}</p>
                        
                        <div class="flex justify-between items-center mt-4">
                            <span class="text-green-600 font-bold text-xl">${{ number_format($auction->current_price, 2) }}</span>
                            <a href="{{ route('auctions.show', $auction) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Bid Now
                            </a>
                        </div>
                        <div class="text-xs text-gray-400 mt-2">
                            Ends: {{ $auction->end_time->diffForHumans() }}
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="mt-6">
                {{ $auctions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>