<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Auction Insights: {{ $auction->title }}</h2>
    </x-slot>

    @php
        $funnel = $insights['funnel'] ?? [
            'views' => 0,
            'watchers' => 0,
            'bidders' => 0,
            'winner' => 0,
        ];

        $rates = $insights['funnel_rates'] ?? [
            'view_to_watch' => 0,
            'watch_to_bid' => 0,
            'bid_to_win' => 0,
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-xl">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Bid Velocity</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $insights['current_bid_velocity'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-xl">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Predicted Final</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format((float) ($insights['predicted_final_price'] ?? 0), 2) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-xl">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Watcher to Bidder</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format((float) ($insights['watcher_to_bidder_conversion_rate'] ?? 0), 1) }}%</p>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-xl">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Health Score</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $insights['health_score'] ?? 0 }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Conversion Funnel</h3>

                <div class="space-y-3">
                    <div class="mx-auto w-full max-w-4xl overflow-hidden text-white bg-[#1f3425]" style="clip-path: polygon(10% 0%, 90% 0%, 100% 100%, 0% 100%);">
                        <div class="grid grid-cols-3 items-center gap-2 px-[12%] py-4 text-sm font-semibold tabular-nums sm:text-base">
                            <span class="min-w-0">Views</span>
                            <span class="text-center">{{ $funnel['views'] }}</span>
                            <span class="text-right">100%</span>
                        </div>
                    </div>

                    <div class="mx-auto w-[88%] max-w-4xl overflow-hidden text-white bg-[#355e3b]" style="clip-path: polygon(10% 0%, 90% 0%, 100% 100%, 0% 100%);">
                        <div class="grid grid-cols-3 items-center gap-2 px-[12%] py-4 text-sm font-semibold tabular-nums sm:text-base">
                            <span class="min-w-0">Watchers</span>
                            <span class="text-center">{{ $funnel['watchers'] }}</span>
                            <span class="text-right">{{ number_format((float) $rates['view_to_watch'], 1) }}%</span>
                        </div>
                    </div>

                    <div class="mx-auto w-[76%] max-w-4xl overflow-hidden text-white bg-[#7a5a2b]" style="clip-path: polygon(10% 0%, 90% 0%, 100% 100%, 0% 100%);">
                        <div class="grid grid-cols-3 items-center gap-2 px-[12%] py-4 text-sm font-semibold tabular-nums sm:text-base">
                            <span class="min-w-0">Bidders</span>
                            <span class="text-center">{{ $funnel['bidders'] }}</span>
                            <span class="text-right">{{ number_format((float) $rates['watch_to_bid'], 1) }}%</span>
                        </div>
                    </div>

                    <div class="mx-auto w-[64%] max-w-4xl overflow-hidden text-white bg-green-600" style="clip-path: polygon(10% 0%, 90% 0%, 100% 100%, 0% 100%);">
                        <div class="grid grid-cols-3 items-center gap-2 px-[12%] py-4 text-sm font-semibold tabular-nums sm:text-base">
                            <span class="min-w-0">Winner</span>
                            <span class="text-center">{{ $funnel['winner'] }}</span>
                            <span class="text-right">{{ number_format((float) $rates['bid_to_win'], 1) }}%</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <p>{{ number_format((float) $rates['view_to_watch'], 1) }}% of viewers watched this auction.</p>
                    <p>{{ number_format((float) $rates['watch_to_bid'], 1) }}% of watchers placed a bid.</p>
                    <p>
                        @if((float) $rates['bid_to_win'] === 0.0)
                            No winner yet.
                        @else
                            Auction sold.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
