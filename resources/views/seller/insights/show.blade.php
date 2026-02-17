<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Auction Insights: {{ $auction->title }}</h2></x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded">Bid velocity: <strong>{{ $insights['current_bid_velocity'] }}</strong></div>
                <div class="bg-white p-4 rounded">Predicted final: <strong>${{ number_format($insights['predicted_final_price'],2) }}</strong></div>
                <div class="bg-white p-4 rounded">Watcher→Bidder: <strong>{{ $insights['watcher_to_bidder_conversion_rate'] }}%</strong></div>
                <div class="bg-white p-4 rounded">Health score: <strong>{{ $insights['health_score'] }}</strong></div>
            </div>
        </div>
    </div>
</x-app-layout>
