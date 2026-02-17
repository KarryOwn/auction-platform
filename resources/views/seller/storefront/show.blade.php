<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $seller->name }}'s Storefront</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded shadow-sm flex gap-4 items-center">
                <img src="{{ $seller->seller_avatar_path ? asset('storage/'.$seller->seller_avatar_path) : 'https://via.placeholder.com/80' }}" class="w-20 h-20 rounded-full object-cover" alt="avatar">
                <div>
                    <h3 class="font-semibold text-lg">{{ $seller->name }} <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Verified Seller</span></h3>
                    <p class="text-sm text-gray-600">{{ $seller->seller_bio ?: 'No bio yet.' }}</p>
                    <p class="text-xs text-gray-500 mt-1">Member since {{ $seller->created_at->toDateString() }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded">Total listed: <strong>{{ $stats['total_listed'] }}</strong></div>
                <div class="bg-white p-4 rounded">Completed sales: <strong>{{ $stats['total_completed'] }}</strong></div>
            </div>

            <div class="bg-white p-6 rounded shadow-sm">
                <h3 class="font-semibold mb-3">Active Auctions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($activeAuctions as $auction)
                        <div class="border rounded p-3">
                            <a class="font-medium text-indigo-600" href="{{ route('auctions.show', $auction) }}">{{ $auction->title }}</a>
                            <p class="text-sm text-gray-600">${{ number_format($auction->current_price, 2) }}</p>
                        </div>
                    @endforeach
                </div>
                {{ $activeAuctions->links() }}
            </div>

            <div class="bg-white p-6 rounded shadow-sm">
                <h3 class="font-semibold mb-3">Completed Auctions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($completedAuctions as $auction)
                        <div class="border rounded p-3">
                            <p class="font-medium">{{ $auction->title }}</p>
                            <p class="text-sm text-gray-600">Sold: ${{ number_format($auction->winning_bid_amount, 2) }}</p>
                        </div>
                    @endforeach
                </div>
                {{ $completedAuctions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
