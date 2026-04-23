<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-gray-900 leading-tight">
            {{ __('My Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            {{-- Welcome Section --}}
            <div class="bg-indigo-600 rounded-2xl shadow-lg p-8 text-white flex flex-col md:flex-row items-center justify-between">
                <div>
                    <h3 class="text-3xl font-bold mb-2">Welcome back, {{ explode(' ', $user->name)[0] }}!</h3>
                    <p class="text-indigo-100 text-lg">Here's what's happening with your auctions today.</p>
                </div>
                <div class="mt-6 md:mt-0 flex gap-4">
                    <a href="{{ route('auctions.index') }}" class="px-6 py-3 bg-white text-indigo-600 font-semibold rounded-lg shadow-sm hover:bg-gray-50 hover:shadow transition-all">
                        Browse Auctions
                    </a>
                    <a href="{{ route('user.wallet') }}" class="px-6 py-3 bg-indigo-500 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-400 hover:shadow transition-all">
                        My Wallet
                    </a>
                </div>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Active Bids Card --}}
                <div class="bg-white shadow-sm rounded-2xl p-6 border border-gray-100 hover:shadow-md transition-shadow relative overflow-hidden">
                    <div class="absolute right-0 top-0 mt-4 mr-4 text-indigo-100">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </div>
                    <div class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Active Bids</div>
                    <div class="mt-1 text-4xl font-extrabold text-indigo-600">{{ $activeBidCount }}</div>
                    <a href="{{ route('user.bids') }}" class="text-sm text-indigo-500 font-medium hover:text-indigo-700 mt-4 inline-flex items-center gap-1 group">
                        View active bids 
                        <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>
                {{-- Won Items Card --}}
                <div class="bg-white shadow-sm rounded-2xl p-6 border border-gray-100 hover:shadow-md transition-shadow relative overflow-hidden">
                    <div class="absolute right-0 top-0 mt-4 mr-4 text-orange-100">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    </div>
                    <div class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Won (Unpaid)</div>
                    <div class="mt-1 text-4xl font-extrabold text-orange-600">{{ $wonUnpaidCount }}</div>
                    <a href="{{ route('user.won-auctions') }}" class="text-sm text-orange-500 font-medium hover:text-orange-700 mt-4 inline-flex items-center gap-1 group">
                        Manage won items 
                        <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>
                {{-- Wallet Balance Card --}}
                <div class="bg-white shadow-sm rounded-2xl p-6 border border-gray-100 hover:shadow-md transition-shadow relative overflow-hidden">
                    <div class="absolute right-0 top-0 mt-4 mr-4 text-green-100">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Wallet Balance</div>
                    <div class="mt-1 text-4xl font-extrabold text-green-600">${{ number_format($user->wallet_balance, 2) }}</div>
                    <a href="{{ route('user.wallet') }}" class="text-sm text-green-500 font-medium hover:text-green-700 mt-4 inline-flex items-center gap-1 group">
                        Manage wallet 
                        <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-indigo-100 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-wider text-indigo-500">Referral Program</p>
                        <h3 class="mt-2 text-2xl font-bold text-gray-900">Invite friends and earn wallet credits</h3>
                        <p class="mt-2 text-sm text-gray-600">Share your personal referral link and track pending versus credited rewards from your dashboard.</p>
                    </div>
                    <a href="{{ route('user.referrals') }}" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700">
                        Open Referrals
                    </a>
                </div>
            </div>

            @if($latestExportRequest)
                <div class="rounded-2xl border px-5 py-4 shadow-sm {{ $latestExportRequest->status === 'ready' ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white' }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Data export</p>
                            <p class="mt-1 text-sm text-gray-600">
                                Latest export status: {{ ucfirst($latestExportRequest->status) }}.
                                @if($latestExportRequest->status === 'ready' && $latestExportRequest->expires_at)
                                    Download before {{ $latestExportRequest->expires_at->format('M d, Y') }}.
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-3">
                            @if($latestExportRequest->status === 'ready')
                                <a href="{{ route('user.data-export.download', $latestExportRequest) }}"
                                   class="inline-flex items-center justify-center rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">
                                    Download Export
                                </a>
                            @else
                                <a href="{{ route('profile.edit') }}"
                                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Manage Export
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                {{-- Main Content - Left 2 Columns --}}
                <div class="xl:col-span-2 space-y-8">
                    
                    {{-- Active Bids List --}}
                    <div class="bg-white shadow-sm sm:rounded-2xl p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 border-l-4 border-indigo-500 pl-3">Active Bids</h3>
                                <p class="text-sm text-gray-500 mt-1 pl-4">Auctions you are currently participating in.</p>
                            </div>
                            <a href="{{ route('user.bids') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-4 py-2 rounded-lg hover:bg-indigo-100 transition">View All</a>
                        </div>

                        @if($activeBids->isEmpty())
                            <div class="text-center py-10 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <p class="mt-4 text-gray-500 font-medium">No active bids right now.</p>
                                <a href="{{ route('auctions.index') }}" class="mt-2 inline-block text-indigo-600 hover:underline">Find something to bid on</a>
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach($activeBids as $bid)
                                    <a href="{{ route('auctions.show', $bid->auction) }}" 
                                       class="flex flex-col sm:flex-row items-center gap-5 p-4 rounded-xl border border-gray-100 hover:shadow-md hover:border-indigo-100 transition-all bg-white group"
                                       x-data="{ auctionId: {{ $bid->auction_id }}, status: '{{ $bid->is_winning ? 'winning' : 'outbid' }}' }"
                                       @outbid-notification.window="if ($event.detail.auctionId == auctionId && $event.detail.type === 'outbid') { status = 'outbid' }"
                                    >
                                        <div class="w-full sm:w-24 h-32 sm:h-24 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0 relative">
                                            @if($bid->auction->getCoverImageUrl('thumbnail'))
                                                <img src="{{ $bid->auction->getCoverImageUrl('thumbnail') }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                            @endif
                                            <div class="absolute inset-0 ring-1 ring-inset ring-black/10 rounded-lg"></div>
                                        </div>
                                        
                                        <div class="flex-1 w-full flex flex-col justify-between py-1">
                                            <div>
                                                <h4 class="font-bold text-gray-900 text-lg leading-tight group-hover:text-indigo-600 transition-colors line-clamp-1">{{ $bid->auction->title }}</h4>
                                                <div class="mt-1 flex items-center gap-3 text-sm text-gray-500">
                                                    <span class="flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        Ends: {{ $bid->auction->end_time->diffForHumans() }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-3 sm:mt-0 flex items-center gap-4">
                                                <div>
                                                    <div class="text-xs text-gray-500 font-medium uppercase tracking-wider">Your max bid</div>
                                                    <div class="text-indigo-600 font-bold">${{ number_format($bid->amount, 2) }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500 font-medium uppercase tracking-wider">Current price</div>
                                                    <div class="text-gray-900 font-bold">${{ number_format($bid->auction->current_price, 2) }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="w-full sm:w-auto flex-shrink-0 flex justify-end">
                                            <template x-if="status === 'winning'">
                                                <div class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                                                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                                                    Winning!
                                                </div>
                                            </template>
                                            <template x-if="status === 'outbid'">
                                                <div class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-red-50 text-red-700 border border-red-200 shadow-sm" x-cloak>
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    Outbid
                                                </div>
                                            </template>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Won Items (Action Required) --}}
                    @if($wonItems->isNotEmpty())
                    <div class="bg-orange-50 sm:rounded-2xl p-6 border border-orange-200 shadow-sm relative overflow-hidden">
                        <div class="absolute -right-10 -top-10 text-orange-200/50">
                            <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                        </div>
                        
                        <div class="relative z-10 flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-xl font-bold text-orange-900 border-l-4 border-orange-500 pl-3">Action Required: Won Items</h3>
                                <p class="text-sm text-orange-700 mt-1 pl-4">Great job! Now complete payment to claim your items.</p>
                            </div>
                            <a href="{{ route('user.won-auctions') }}" class="text-sm font-medium text-orange-800 hover:text-orange-900 bg-white shadow-sm px-4 py-2 rounded-lg hover:shadow transition">View All</a>
                        </div>
                        
                        <div class="relative z-10 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($wonItems as $auction)
                                <div class="bg-white rounded-xl p-4 shadow-sm border border-orange-100 hover:shadow-md transition">
                                    <div class="flex gap-4">
                                        <div class="w-20 h-20 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                            @if($auction->getCoverImageUrl('thumbnail'))
                                                <img src="{{ $auction->getCoverImageUrl('thumbnail') }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-bold text-gray-900 truncate">{{ $auction->title }}</div>
                                            <div class="text-orange-600 font-extrabold text-lg mt-1">${{ number_format($auction->winning_bid_amount, 2) }}</div>
                                            <div class="text-xs text-gray-500 mt-1">Won on {{ $auction->closed_at->format('M d') }}</div>
                                        </div>
                                    </div>
                                    <a href="{{ route('auctions.show', $auction) }}" class="mt-4 w-full flex justify-center items-center px-4 py-2 bg-orange-600 text-white rounded-lg text-sm font-semibold hover:bg-orange-700 transition shadow-sm">
                                        Pay & Claim Now
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                </div>

                {{-- Sidebar - Right Column --}}
                <div class="space-y-8">
                    
                    {{-- Watchlist Mini --}}
                    <div class="bg-white shadow-sm sm:rounded-2xl p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 border-l-4 border-pink-500 pl-3">Watchlist</h3>
                            <a href="{{ route('user.watchlist') }}" class="text-sm text-pink-600 hover:underline font-medium">View All</a>
                        </div>

                        @if($watchedItems->isEmpty())
                            <div class="text-center py-8">
                                <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                <p class="text-sm text-gray-500">Nothing here yet.<br>Save items you like!</p>
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach($watchedItems as $watcher)
                                    <a href="{{ route('auctions.show', $watcher->auction) }}" class="flex items-center gap-3 p-3 rounded-xl border border-transparent hover:bg-gray-50 hover:border-gray-100 transition group">
                                        <div class="w-14 h-14 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                            @if($watcher->auction->getCoverImageUrl('thumbnail'))
                                                <img src="{{ $watcher->auction->getCoverImageUrl('thumbnail') }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-900 truncate text-sm">{{ $watcher->auction->title }}</div>
                                            <div class="text-sm text-gray-900 font-bold mt-0.5">${{ number_format($watcher->auction->current_price, 2) }}</div>
                                        </div>
                                        <div class="flex-shrink-0 text-right">
                                            <div class="text-xs font-semibold px-2 py-1 rounded bg-orange-100 text-orange-800 whitespace-nowrap">
                                                {{ $watcher->auction->timeRemaining() }}
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Quick Links / Help --}}
                    <div class="theme-card p-6 mt-4">
                        <h3 class="text-lg font-bold border-l-2 border-brand pl-3 mb-4 text-gray-900">Need Help?</h3>
                        <ul class="space-y-3 text-sm">
                            <li><a href="#" class="theme-link flex items-center gap-2"><svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> How bidding works</a></li>
                            <li><a href="#" class="theme-link flex items-center gap-2"><svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg> Payment methods</a></li>
                            <li><a href="#" class="theme-link flex items-center gap-2"><svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path></svg> Contact support</a></li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
