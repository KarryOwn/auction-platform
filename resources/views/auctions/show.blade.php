<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $auction->title }}
            </h2>
            <div class="flex items-center gap-3">
                {{-- Status Badge --}}
                @if($auction->isActive())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1.5 animate-pulse"></span> Live
                    </span>
                @elseif($auction->status === \App\Models\Auction::STATUS_COMPLETED)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Ended</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">{{ ucfirst($auction->status) }}</span>
                @endif

                {{-- Watch Button --}}
                @auth
                <button id="watch-btn"
                        onclick="toggleWatch()"
                        class="inline-flex items-center px-3 py-1.5 border rounded-lg text-sm font-medium transition
                               {{ $isWatching ? 'border-yellow-400 bg-yellow-50 text-yellow-700' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50' }}">
                    <svg class="w-4 h-4 mr-1" fill="{{ $isWatching ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span id="watch-text">{{ $isWatching ? 'Watching' : 'Watch' }}</span>
                </button>
                @endauth
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Main Grid: Info + Bidding --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Left Column: Auction Details --}}
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <p class="text-gray-700 leading-relaxed mb-6">{{ $auction->description }}</p>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Starting Price</span>
                                <span class="text-lg font-bold text-gray-800">${{ number_format($auction->starting_price, 2) }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Bids</span>
                                <span id="bid-count" class="text-lg font-bold text-gray-800">{{ $auction->bids_count ?? $auction->bid_count ?? 0 }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Min Increment</span>
                                <span class="text-lg font-bold text-gray-800">${{ number_format($auction->min_bid_increment, 2) }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Seller</span>
                                <span class="text-lg font-bold text-gray-800">{{ $auction->seller->name ?? 'N/A' }}</span>
                            </div>
                        </div>

                        {{-- Reserve Price Indicator --}}
                        @if($auction->hasReserve())
                            <div class="mt-4 flex items-center gap-2 text-sm">
                                @if($auction->reserve_met)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-green-100 text-green-800 font-medium">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Reserve Met
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-800 font-medium">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        Reserve Not Met
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Recent Bids --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Bids</h3>
                        <div id="bid-history" class="divide-y divide-gray-100">
                            @forelse($recentBids as $bid)
                                <div class="flex items-center justify-between py-3 bid-entry">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-semibold text-sm">
                                            {{ strtoupper(substr($bid->user->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">{{ $bid->user->name ?? 'Unknown' }}</span>
                                            @if($bid->bid_type === \App\Models\Bid::TYPE_AUTO)
                                                <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Auto</span>
                                            @endif
                                            @if($bid->is_snipe_bid)
                                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Snipe</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-bold text-gray-900">${{ number_format($bid->amount, 2) }}</span>
                                        <span class="block text-xs text-gray-400">{{ $bid->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-400 text-sm py-4 text-center">No bids yet. Be the first!</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Right Column: Bidding Panel --}}
                <div class="space-y-6">

                    {{-- Price & Timer Card --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="text-center mb-6">
                            <span class="block text-sm text-gray-500 uppercase tracking-wide">Current Price</span>
                            <span id="price-display" class="text-5xl font-black text-green-600 transition-colors duration-300">
                                ${{ number_format($auction->current_price, 2) }}
                            </span>
                            @if($auction->highestBid && $auction->highestBid->user)
                                <p class="text-xs text-gray-400 mt-1">
                                    by <span id="highest-bidder" class="font-medium">{{ $auction->highestBid->user->name }}</span>
                                </p>
                            @endif
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 text-center mb-6">
                            <span class="block text-xs text-gray-500 uppercase tracking-wide">Time Remaining</span>
                            <span id="countdown" class="text-2xl font-bold text-gray-800" data-end="{{ $auction->end_time->toIso8601String() }}">
                                {{ $auction->timeRemaining() }}
                            </span>
                            @if($auction->extension_count > 0)
                                <span class="block text-xs text-orange-500 mt-1">
                                    Extended {{ $auction->extension_count }}×
                                </span>
                            @endif
                        </div>

                        {{-- Snipe Warning --}}
                        <div id="snipe-warning" class="hidden bg-orange-50 border border-orange-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center gap-2 text-orange-700 text-sm font-medium">
                                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Anti-snipe active — bids may extend the auction by {{ $auction->snipe_extension_seconds }}s
                            </div>
                        </div>

                        {{-- Bid Form --}}
                        @if($auction->isActive())
                            <div id="error-message" class="hidden bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg mb-4 text-sm"></div>
                            <div id="success-message" class="hidden bg-green-50 border border-green-200 text-green-700 p-3 rounded-lg mb-4 text-sm"></div>

                            <form id="bid-form" class="space-y-4">
                                <div>
                                    <label for="bid-amount" class="block text-sm font-medium text-gray-700 mb-1">
                                        Your Bid <span class="text-gray-400">(min $<span id="min-bid">{{ number_format($auction->minimumNextBid(), 2) }}</span>)</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 text-lg">$</span>
                                        <input type="number" id="bid-amount" step="0.01"
                                               min="{{ $auction->minimumNextBid() }}"
                                               value="{{ $auction->minimumNextBid() }}"
                                               class="pl-8 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xl font-semibold">
                                    </div>
                                </div>

                                <button type="submit" id="bid-btn"
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-150 ease-in-out disabled:opacity-50 disabled:cursor-not-allowed">
                                    Place Bid
                                </button>
                            </form>
                        @else
                            <div class="bg-gray-100 rounded-lg p-4 text-center text-gray-500">
                                <p class="text-lg font-semibold">Auction has ended</p>
                                @if($auction->winner)
                                    <p class="mt-1">Won by <span class="font-bold text-gray-800">{{ $auction->winner->name }}</span></p>
                                    <p class="text-green-600 font-bold text-xl mt-1">${{ number_format($auction->winning_bid_amount, 2) }}</p>
                                @else
                                    <p class="mt-1 text-sm">No winner determined.</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Auto-Bid Card --}}
                    @auth
                    @if($auction->isActive())
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/>
                            </svg>
                            Auto-Bid
                        </h3>

                        <div id="auto-bid-status">
                            @if($autoBid)
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                                    <p class="text-sm text-blue-800">
                                        Active up to <span class="font-bold">${{ number_format($autoBid->max_amount, 2) }}</span>
                                    </p>
                                    <button onclick="cancelAutoBid()" class="mt-2 text-xs text-red-600 hover:text-red-800 font-medium underline">
                                        Cancel Auto-Bid
                                    </button>
                                </div>
                            @endif
                        </div>

                        <form id="auto-bid-form" class="{{ $autoBid ? 'hidden' : '' }}">
                            <label for="auto-bid-max" class="block text-xs text-gray-500 mb-1">Max bid amount</label>
                            <input type="number" id="auto-bid-max" step="0.01"
                                   min="{{ $auction->minimumNextBid() }}"
                                   placeholder="{{ number_format($auction->minimumNextBid() * 2, 2) }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm mb-2">
                            <button type="submit"
                                    class="w-full bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium py-2 px-3 rounded-lg transition">
                                Enable Auto-Bid
                            </button>
                        </form>
                        <p class="text-xs text-gray-400 mt-2">The system will automatically bid on your behalf up to your limit.</p>
                    </div>
                    @endif
                    @endauth

                </div>
            </div>
        </div>
    </div>

    {{-- Countdown Timer Script --}}
    <script>
        (function() {
            const countdownEl = document.getElementById('countdown');
            const snipeWarningEl = document.getElementById('snipe-warning');
            const snipeThreshold = {{ $auction->snipe_threshold_seconds ?? 30 }};
            let endTime = new Date(countdownEl.dataset.end).getTime();

            function updateCountdown() {
                const now = Date.now();
                const diff = endTime - now;

                if (diff <= 0) {
                    countdownEl.textContent = 'Ended';
                    countdownEl.classList.add('text-red-600');
                    if (snipeWarningEl) snipeWarningEl.classList.add('hidden');
                    return;
                }

                // Show snipe warning when within threshold
                const secondsLeft = Math.floor(diff / 1000);
                if (secondsLeft <= snipeThreshold) {
                    snipeWarningEl?.classList.remove('hidden');
                    countdownEl.classList.add('text-orange-600');
                    countdownEl.classList.remove('text-gray-800');
                } else {
                    snipeWarningEl?.classList.add('hidden');
                    countdownEl.classList.remove('text-orange-600');
                    countdownEl.classList.add('text-gray-800');
                }

                const days = Math.floor(diff / 86400000);
                const hours = Math.floor((diff % 86400000) / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                const seconds = Math.floor((diff % 60000) / 1000);

                let parts = [];
                if (days > 0) parts.push(days + 'd');
                if (hours > 0) parts.push(hours + 'h');
                parts.push(minutes + 'm');
                parts.push(seconds + 's');

                countdownEl.textContent = parts.join(' ');
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);

            // Expose a way to extend end time from WebSocket events
            window.updateAuctionEndTime = function(newEndTime) {
                endTime = new Date(newEndTime).getTime();
            };
        })();
    </script>

    {{-- Bid Form Script --}}
    <script>
        document.getElementById('bid-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('bid-btn');
            const amount = document.getElementById('bid-amount').value;
            const errorDiv = document.getElementById('error-message');
            const successDiv = document.getElementById('success-message');

            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            btn.disabled = true;
            btn.textContent = 'Placing...';

            try {
                const response = await fetch("{{ route('auctions.bid', $auction) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ amount })
                });

                const data = await response.json();

                if (response.ok) {
                    successDiv.textContent = data.message;
                    successDiv.classList.remove('hidden');
                    document.getElementById('price-display').innerText = '$' + parseFloat(data.new_price).toFixed(2);
                    // Update min bid
                    const nextMin = (parseFloat(data.new_price) + {{ (float) $auction->min_bid_increment }}).toFixed(2);
                    document.getElementById('min-bid').textContent = nextMin;
                    document.getElementById('bid-amount').min = nextMin;
                    document.getElementById('bid-amount').value = nextMin;
                } else {
                    errorDiv.textContent = data.message || 'Bid rejected.';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Place Bid';
            }
        });
    </script>

    {{-- Watch Toggle --}}
    <script>
        async function toggleWatch() {
            try {
                const response = await fetch("{{ route('auctions.watch', $auction) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    }
                });
                const data = await response.json();
                const btn = document.getElementById('watch-btn');
                const txt = document.getElementById('watch-text');

                if (data.watching) {
                    btn.classList.add('border-yellow-400', 'bg-yellow-50', 'text-yellow-700');
                    btn.classList.remove('border-gray-300', 'bg-white', 'text-gray-600');
                    txt.textContent = 'Watching';
                } else {
                    btn.classList.remove('border-yellow-400', 'bg-yellow-50', 'text-yellow-700');
                    btn.classList.add('border-gray-300', 'bg-white', 'text-gray-600');
                    txt.textContent = 'Watch';
                }
            } catch (error) {
                console.error('Watch toggle failed:', error);
            }
        }
    </script>

    {{-- Auto-Bid Scripts --}}
    <script>
        document.getElementById('auto-bid-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const maxAmount = document.getElementById('auto-bid-max').value;

            try {
                const response = await fetch("{{ route('auctions.auto-bid', $auction) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ max_amount: maxAmount })
                });

                const data = await response.json();
                if (response.ok) {
                    document.getElementById('auto-bid-status').innerHTML = `
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                            <p class="text-sm text-blue-800">Active up to <span class="font-bold">$${parseFloat(maxAmount).toFixed(2)}</span></p>
                            <button onclick="cancelAutoBid()" class="mt-2 text-xs text-red-600 hover:text-red-800 font-medium underline">Cancel Auto-Bid</button>
                        </div>`;
                    document.getElementById('auto-bid-form').classList.add('hidden');
                }
            } catch (error) {
                console.error('Auto-bid setup failed:', error);
            }
        });

        async function cancelAutoBid() {
            try {
                const response = await fetch("{{ route('auctions.cancel-auto-bid', $auction) }}", {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    }
                });
                if (response.ok) {
                    document.getElementById('auto-bid-status').innerHTML = '';
                    document.getElementById('auto-bid-form').classList.remove('hidden');
                }
            } catch (error) {
                console.error('Auto-bid cancel failed:', error);
            }
        }
    </script>

    {{-- Real-Time WebSocket Events --}}
    <script type="module">
        const auctionId = "{{ $auction->id }}";

        Echo.channel(`auctions.${auctionId}`)
            // Price update (backward compatible)
            .listen('.price-updated', (e) => {
                const priceEl = document.getElementById('price-display');
                priceEl.classList.add('text-yellow-500');
                priceEl.innerText = '$' + parseFloat(e.newPrice).toFixed(2);
                setTimeout(() => priceEl.classList.remove('text-yellow-500'), 500);
            })
            // Full bid event
            .listen('.bid.placed', (e) => {
                // Update price
                const priceEl = document.getElementById('price-display');
                priceEl.classList.add('text-yellow-500');
                priceEl.innerText = '$' + parseFloat(e.amount).toFixed(2);
                setTimeout(() => priceEl.classList.remove('text-yellow-500'), 500);

                // Update min bid
                const nextMin = (parseFloat(e.amount) + {{ (float) $auction->min_bid_increment }}).toFixed(2);
                const minBidEl = document.getElementById('min-bid');
                const bidAmountEl = document.getElementById('bid-amount');
                if (minBidEl) minBidEl.textContent = nextMin;
                if (bidAmountEl) {
                    bidAmountEl.min = nextMin;
                    bidAmountEl.value = nextMin;
                }

                // Update highest bidder
                const bidderEl = document.getElementById('highest-bidder');
                if (bidderEl && e.bidder_name) bidderEl.textContent = e.bidder_name;

                // Update bid count
                const countEl = document.getElementById('bid-count');
                if (countEl && e.bid_count) countEl.textContent = e.bid_count;

                // Update end_time if snipe extension
                if (e.end_time) {
                    window.updateAuctionEndTime(e.end_time);
                }

                // Prepend to bid history
                const history = document.getElementById('bid-history');
                if (history) {
                    const typeLabel = e.bid_type === 'auto'
                        ? '<span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Auto</span>'
                        : '';
                    const snipeLabel = e.is_snipe_bid
                        ? '<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Snipe</span>'
                        : '';

                    const entry = document.createElement('div');
                    entry.className = 'flex items-center justify-between py-3 bid-entry bg-green-50 transition-colors duration-1000';
                    entry.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-semibold text-sm">
                                ${(e.bidder_name || '?').charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-900">${e.bidder_name || 'Unknown'}</span>
                                ${typeLabel}${snipeLabel}
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-bold text-gray-900">$${parseFloat(e.amount).toFixed(2)}</span>
                            <span class="block text-xs text-gray-400">just now</span>
                        </div>`;

                    history.prepend(entry);
                    setTimeout(() => entry.classList.remove('bg-green-50'), 2000);

                    // Remove "No bids yet" message
                    const empty = history.querySelector('p.text-gray-400');
                    if (empty) empty.remove();

                    // Keep only last 15 entries
                    const entries = history.querySelectorAll('.bid-entry');
                    if (entries.length > 15) entries[entries.length - 1].remove();
                }
            })
            // Auction closed
            .listen('.auction.closed', (e) => {
                document.getElementById('countdown').textContent = 'Ended';
                document.getElementById('countdown').classList.add('text-red-600');

                const bidForm = document.getElementById('bid-form');
                if (bidForm) {
                    bidForm.innerHTML = `
                        <div class="bg-gray-100 rounded-lg p-4 text-center text-gray-500">
                            <p class="text-lg font-semibold">Auction has ended</p>
                            ${e.winner_id ? `<p class="text-green-600 font-bold text-xl mt-1">$${parseFloat(e.final_price).toFixed(2)}</p>` : '<p class="mt-1 text-sm">No winner determined.</p>'}
                        </div>`;
                }

                const autoBidForm = document.getElementById('auto-bid-form');
                if (autoBidForm) autoBidForm.remove();
            });
    </script>
</x-app-layout>