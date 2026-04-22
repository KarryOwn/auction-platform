<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-700">Comparison</p>
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">Compare Auctions</h2>
        </div>
    </x-slot>

    <div class="py-8 bg-[radial-gradient(circle_at_top_left,_rgba(251,191,36,0.15),_transparent_28%),linear-gradient(180deg,#fff7ed_0%,#f8fafc_35%,#f8fafc_100%)] min-h-screen">
        <div class="max-w-[1400px] mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-2xl border border-amber-200/70 bg-white/90 backdrop-blur shadow-sm px-6 py-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-600">Select up to 4 active auctions and review the differences side by side.</p>
                    <p class="text-xs text-gray-500 mt-1">Missing values appear as `—`. Prices and bid counts refresh automatically.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('auctions.index') }}" class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Browse Auctions
                    </a>
                    <button type="button" class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-lg bg-amber-600 text-sm font-semibold text-white hover:bg-amber-700" onclick="window.AuctionCompareUI?.clear()">
                        Clear Selection
                    </button>
                </div>
            </div>

            @if($comparison && count($comparison['auctions']) >= 2)
                @php
                    $baseRows = [
                        ['key' => 'current_price', 'label' => 'Current Price', 'currency' => true],
                        ['key' => 'next_minimum', 'label' => 'Next Minimum Bid', 'currency' => true],
                        ['key' => 'bid_count', 'label' => 'Bid Count'],
                        ['key' => 'time_remaining', 'label' => 'Time Remaining', 'dynamic' => true],
                        ['key' => 'condition', 'label' => 'Condition'],
                        ['key' => 'brand', 'label' => 'Brand'],
                        ['key' => 'category', 'label' => 'Category'],
                        ['key' => 'reserve_met', 'label' => 'Reserve Status', 'reserve' => true],
                    ];
                @endphp

                <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-xl shadow-amber-100/50">
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-separate border-spacing-0" id="comparison-table">
                            <thead class="sticky top-0 z-20 bg-white">
                                <tr>
                                    <th class="sticky left-0 z-30 min-w-[220px] bg-white px-6 py-5 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500 border-b border-r border-gray-200">
                                        Item
                                    </th>
                                    @foreach($comparison['auctions'] as $auction)
                                        <th class="min-w-[280px] px-5 py-5 align-top border-b border-gray-200 bg-white/95">
                                            <div class="flex flex-col gap-4">
                                                <div class="aspect-[4/3] overflow-hidden rounded-2xl bg-gray-100 border border-gray-200">
                                                    @if($auction['thumbnail_url'])
                                                        <img src="{{ $auction['thumbnail_url'] }}" alt="{{ $auction['title'] }}" class="h-full w-full object-cover">
                                                    @else
                                                        <div class="h-full w-full grid place-items-center text-sm text-gray-400">No image</div>
                                                    @endif
                                                </div>
                                                <div class="space-y-2">
                                                    <a href="{{ $auction['url'] }}" class="line-clamp-2 text-lg font-bold text-gray-900 hover:text-indigo-600">
                                                        {{ $auction['title'] }}
                                                    </a>
                                                    <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                                                        @if($auction['condition'])
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 font-semibold">{{ $auction['condition'] }}</span>
                                                        @endif
                                                        @if($auction['brand'])
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-gray-100 text-gray-700 font-semibold">{{ $auction['brand'] }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($baseRows as $row)
                                    <tr>
                                        <th class="sticky left-0 z-10 bg-gray-50 px-6 py-4 text-left text-sm font-semibold text-gray-700 border-r border-b border-gray-200">
                                            {{ $row['label'] }}
                                        </th>
                                        @foreach($comparison['auctions'] as $auction)
                                            <td class="px-5 py-4 border-b border-gray-200 text-sm text-gray-700"
                                                @if(!empty($row['dynamic'])) data-compare-auction-id="{{ $auction['id'] }}" @endif>
                                                @if(!empty($row['currency']))
                                                    <span class="text-base font-bold text-gray-900"
                                                          data-field="{{ $row['key'] }}"
                                                          data-auction-id="{{ $auction['id'] }}">
                                                        ${{ number_format((float) $auction[$row['key']], 2) }}
                                                    </span>
                                                @elseif(!empty($row['reserve']))
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $auction['reserve_met'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                                        {{ $auction['reserve_met'] ? 'Reserve met' : 'Reserve not met' }}
                                                    </span>
                                                @else
                                                    <span data-field="{{ $row['key'] }}" data-auction-id="{{ $auction['id'] }}">
                                                        {{ $auction[$row['key']] ?: '—' }}
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                @foreach($comparison['attribute_columns'] as $attribute)
                                    <tr>
                                        <th class="sticky left-0 z-10 bg-white px-6 py-4 text-left text-sm font-semibold text-gray-700 border-r border-b border-gray-200">
                                            {{ $attribute['name'] }}
                                        </th>
                                        @foreach($comparison['auctions'] as $auction)
                                            <td class="px-5 py-4 border-b border-gray-200 text-sm text-gray-700">
                                                {{ $auction['attributes'][$attribute['slug']] ?? '—' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <tr>
                                    <th class="sticky left-0 z-10 bg-gray-50 px-6 py-4 text-left text-sm font-semibold text-gray-700 border-r border-b border-gray-200">
                                        Action
                                    </th>
                                    @foreach($comparison['auctions'] as $auction)
                                        <td class="px-5 py-4 border-b border-gray-200">
                                            <div class="flex flex-wrap gap-3">
                                                <a href="{{ $auction['url'] }}" class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-lg bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700">
                                                    Open Auction
                                                </a>
                                                <button type="button" class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                                        onclick="window.AuctionCompareUI?.remove({{ $auction['id'] }}, true)">
                                                    Remove
                                                </button>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="rounded-3xl border border-dashed border-gray-300 bg-white px-8 py-16 text-center shadow-sm">
                    <h3 class="text-xl font-bold text-gray-900">Pick at least two active auctions to compare</h3>
                    <p class="mt-2 text-sm text-gray-500">Use the compare checkbox on auction cards. You can keep up to four auctions in the tray.</p>
                    <a href="{{ route('auctions.index') }}" class="mt-6 inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-lg bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700">
                        Start Comparing
                    </a>
                </div>
            @endif
        </div>
    </div>

    @include('auctions.partials.compare-bar')

    @push('scripts')
        @include('auctions.partials.compare-script', ['pollComparedAuctions' => $comparison['auctions'] ?? []])
    @endpush
</x-app-layout>
