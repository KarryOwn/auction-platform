<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Won Auctions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Tabs --}}
            <div class="mb-6 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    @foreach(['pending' => 'Pending Payment', 'paid' => 'Paid', 'all' => 'All'] as $key => $label)
                        <a href="{{ route('user.won-auctions', ['tab' => $key]) }}"
                           class="py-4 px-1 border-b-2 font-medium text-sm {{ $tab === $key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>

            @if($auctions->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                    <p class="text-gray-400 text-lg">No won auctions in this category.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($auctions as $auction)
                        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden hover:shadow-md transition">
                            <div class="aspect-video bg-gray-100">
                                @if($auction->getCoverImageUrl('gallery'))
                                    <img src="{{ $auction->getCoverImageUrl('gallery') }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">No image</div>
                                @endif
                            </div>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-900 truncate">{{ $auction->title }}</h3>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-green-600 font-bold text-xl">${{ number_format($auction->winning_bid_amount, 2) }}</span>
                                    @php
                                        $statusConfig = [
                                            'pending' => ['bg-orange-100 text-orange-800', 'Pending'],
                                            'paid'    => ['bg-green-100 text-green-800', 'Paid'],
                                            'failed'  => ['bg-red-100 text-red-800', 'Failed'],
                                            'expired' => ['bg-gray-100 text-gray-800', 'Expired'],
                                            'none'    => ['bg-gray-100 text-gray-600', 'N/A'],
                                        ];
                                        [$statusClass, $statusLabel] = $statusConfig[$auction->payment_status] ?? $statusConfig['none'];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-400 mt-2">
                                    Won {{ $auction->closed_at?->diffForHumans() }}
                                </div>
                                @if($auction->payment_status === 'pending')
                                    <a href="{{ route('auctions.show', $auction) }}" class="mt-3 w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                                        Pay Now
                                    </a>
                                @else
                                    <a href="{{ route('auctions.show', $auction) }}" class="mt-3 w-full inline-flex justify-center items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
                                        View Auction
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $auctions->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
