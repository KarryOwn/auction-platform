<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Seller Analytics</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex gap-2 items-end">
                <div><label class="block text-xs">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded border-gray-300"></div>
                <div><label class="block text-xs">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded border-gray-300"></div>
                <button class="px-3 py-2 bg-gray-800 text-white rounded">Apply</button>
            </form>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="bg-white p-3 rounded">Views: {{ $metrics['total_views'] }}</div>
                <div class="bg-white p-3 rounded">Unique viewers: {{ $metrics['unique_viewers'] }}</div>
                <div class="bg-white p-3 rounded">Bids: {{ $metrics['total_bids'] }}</div>
                <div class="bg-white p-3 rounded">Unique bidders: {{ $metrics['unique_bidders'] }}</div>
                <div class="bg-white p-3 rounded">Watchers: {{ $metrics['watchers'] }}</div>
            </div>

            <div class="bg-white p-6 rounded shadow-sm">
                <h3 class="font-semibold mb-2">Top performing auctions</h3>
                <table class="min-w-full text-sm">
                    <thead><tr><th class="text-left">Auction</th><th class="text-left">Views</th><th class="text-left">Bids</th></tr></thead>
                    <tbody>
                    @foreach($topAuctions as $auction)
                        <tr class="border-t"><td>{{ $auction->title }}</td><td>{{ $auction->views_count }}</td><td>{{ $auction->bids_count }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
