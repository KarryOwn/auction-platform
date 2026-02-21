<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Active Bids</div>
                    <div class="mt-1 text-3xl font-bold text-indigo-600">{{ $activeBidCount }}</div>
                    <a href="{{ route('user.bids') }}" class="text-sm text-indigo-500 hover:underline mt-2 inline-block">View all bids →</a>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Won (Unpaid)</div>
                    <div class="mt-1 text-3xl font-bold text-orange-600">{{ $wonUnpaidCount }}</div>
                    <a href="{{ route('user.won-auctions') }}" class="text-sm text-orange-500 hover:underline mt-2 inline-block">View won items →</a>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Wallet Balance</div>
                    <div class="mt-1 text-3xl font-bold text-green-600">${{ number_format($user->wallet_balance, 2) }}</div>
                    <a href="{{ route('user.wallet') }}" class="text-sm text-green-500 hover:underline mt-2 inline-block">Manage wallet →</a>
                </div>
            </div>

            {{-- Active Bids --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Active Bids</h3>
                    <a href="{{ route('user.bids') }}" class="text-sm text-indigo-600 hover:underline">View All</a>
                </div>

                @if($activeBids->isEmpty())
                    <p class="text-gray-400 text-sm">You haven't placed any bids on active auctions.</p>
                @else
                    <div class="space-y-3">
                        @foreach($activeBids as $bid)
                            <a href="{{ route('auctions.show', $bid->auction) }}"
                               class="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition"
                               x-data="{ auctionId: {{ $bid->auction_id }}, status: '{{ $bid->is_winning ? 'winning' : 'outbid' }}' }"
                               @outbid-notification.window="if ($event.detail.auctionId == auctionId && $event.detail.type === 'outbid') { status = 'outbid' }"
                            >
                                <div class="w-16 h-16 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                                    @if($bid->auction->getCoverImageUrl('thumbnail'))
                                        <img src="{{ $bid->auction->getCoverImageUrl('thumbnail') }}" alt="" class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-900 truncate">{{ $bid->auction->title }}</div>
                                    <div class="text-sm text-gray-500">Your bid: ${{ number_format($bid->amount, 2) }}</div>
                                </div>
                                <div class="flex-shrink-0 text-right">
                                    <span x-show="status === 'winning'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Winning</span>
                                    <span x-show="status === 'outbid'" x-cloak class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 animate-pulse">Outbid</span>
                                    <div class="text-xs text-gray-400 mt-1">{{ $bid->auction->timeRemaining() }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Won Items --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Won Items (Pending Payment)</h3>
                    <a href="{{ route('user.won-auctions') }}" class="text-sm text-indigo-600 hover:underline">View All</a>
                </div>

                @if($wonItems->isEmpty())
                    <p class="text-gray-400 text-sm">No items pending payment.</p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($wonItems as $auction)
                            <div class="border rounded-lg p-4 hover:shadow-md transition">
                                <div class="aspect-video bg-gray-100 rounded overflow-hidden mb-3">
                                    @if($auction->getCoverImageUrl('thumbnail'))
                                        <img src="{{ $auction->getCoverImageUrl('thumbnail') }}" alt="" class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <div class="font-medium text-gray-900 truncate">{{ $auction->title }}</div>
                                <div class="text-green-600 font-bold mt-1">${{ number_format($auction->winning_bid_amount, 2) }}</div>
                                <a href="{{ route('auctions.show', $auction) }}" class="mt-3 w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                                    Pay Now
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Watchlist --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Watchlist (Ending Soon)</h3>
                    <a href="{{ route('user.watchlist') }}" class="text-sm text-indigo-600 hover:underline">View All</a>
                </div>

                @if($watchedItems->isEmpty())
                    <p class="text-gray-400 text-sm">Your watchlist is empty. Browse auctions and click the heart icon to watch items.</p>
                @else
                    <div class="space-y-3">
                        @foreach($watchedItems as $watcher)
                            <a href="{{ route('auctions.show', $watcher->auction) }}" class="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition">
                                <div class="w-16 h-16 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                                    @if($watcher->auction->getCoverImageUrl('thumbnail'))
                                        <img src="{{ $watcher->auction->getCoverImageUrl('thumbnail') }}" alt="" class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-900 truncate">{{ $watcher->auction->title }}</div>
                                    <div class="text-sm text-green-600 font-semibold">${{ number_format($watcher->auction->current_price, 2) }}</div>
                                </div>
                                <div class="flex-shrink-0 text-right">
                                    <div class="text-sm text-orange-600 font-medium">{{ $watcher->auction->timeRemaining() }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
