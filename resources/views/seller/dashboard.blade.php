<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Seller Dashboard</h2>
            <a href="{{ route('seller.auctions.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">Create New Auction</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded">Total Auctions: <strong id="m-total">{{ $stats['total_auctions'] }}</strong></div>
                <div class="bg-white p-4 rounded">Revenue: <strong>${{ number_format($stats['total_revenue'],2) }}</strong></div>
                <div class="bg-white p-4 rounded">Bids: <strong>{{ $stats['total_bids_received'] }}</strong></div>
                <div class="bg-white p-4 rounded">Unread Msg: <strong id="m-unread">{{ $stats['unread_messages'] }}</strong></div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded text-sm">
                Draft auctions waiting to publish: <strong>{{ $stats['draft_auctions'] }}</strong>
            </div>

            <div class="bg-white p-6 rounded shadow-sm">
                <h3 class="font-semibold mb-3">Active Listings</h3>
                <table class="min-w-full text-sm">
                    <thead><tr><th class="text-left">Auction</th><th class="text-left">Current</th><th class="text-left">Bids</th><th class="text-left">Ends</th></tr></thead>
                    <tbody>
                    @foreach($activeListings as $auction)
                        <tr class="border-t">
                            <td class="py-2">
                                <div class="flex items-center gap-2">
                                    @if($auction->getCoverImageUrl())
                                        <img src="{{ $auction->getCoverImageUrl() }}" class="w-10 h-10 rounded object-cover" alt="{{ $auction->title }}">
                                    @else
                                        <div class="w-10 h-10 rounded bg-gray-100"></div>
                                    @endif
                                    <a class="text-indigo-600" href="{{ route('auctions.show', $auction) }}">{{ $auction->title }}</a>
                                </div>
                            </td>
                            <td>${{ number_format($auction->current_price,2) }}</td>
                            <td>{{ $auction->bids_count }}</td>
                            <td>{{ optional($auction->end_time)->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-white p-6 rounded shadow-sm">
                <h3 class="font-semibold mb-3">Recent Activity</h3>
                <ul class="space-y-2">
                    @foreach($recentActivity as $bid)
                        <li class="text-sm">{{ $bid->user?->name }} bid ${{ number_format($bid->amount,2) }} on {{ $bid->auction?->title }} ({{ $bid->created_at->diffForHumans() }})</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <script type="module">
        Echo.private(`seller.{{ auth()->id() }}`)
            .listen('.new.bid.on.listing', () => refreshMetrics())
            .listen('.message.sent', () => refreshMetrics())
            .listen('.auction.ended.for.seller', () => refreshMetrics());

        async function refreshMetrics() {
            const res = await fetch('{{ route('seller.metrics.live') }}', { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            document.getElementById('m-unread').textContent = json.unread_messages;
        }
    </script>
</x-app-layout>
