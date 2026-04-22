<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Notification Preferences') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <p class="text-green-800 text-sm">{{ session('success') }}</p>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600 mb-6">Choose how you want to be notified for each type of event. Critical account notifications (password resets, security alerts) cannot be disabled.</p>

                <form method="POST" action="{{ route('user.notification-preferences.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="locale" class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                            <input
                                type="text"
                                id="locale"
                                name="locale"
                                value="{{ old('locale', auth()->user()->userPreference?->locale ?? app()->getLocale()) }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="en"
                            >
                        </div>

                        <div>
                            <label for="display_currency" class="block text-sm font-medium text-gray-700 mb-1">Display Currency</label>
                            <select
                                id="display_currency"
                                name="display_currency"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                @php($selectedCurrency = old('display_currency', auth()->user()->userPreference?->display_currency ?? display_currency()))
                                @foreach($supportedCurrencies as $currency)
                                    <option value="{{ $currency }}" @selected($selectedCurrency === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <h3 class="text-sm font-semibold text-amber-950">Watchlist defaults</h3>
                        <p class="mt-1 text-sm text-amber-900">Use this as the starting point when you watch a listing. You can still override it per auction.</p>
                        <div class="mt-4 max-w-sm">
                            <label for="default_outbid_threshold" class="block text-sm font-medium text-gray-700 mb-1">Only notify me when I am outbid by at least</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500">$</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    id="default_outbid_threshold"
                                    name="default_outbid_threshold"
                                    value="{{ old('default_outbid_threshold', auth()->user()->default_outbid_threshold) }}"
                                    class="w-full rounded-lg border-gray-300 pl-7 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    placeholder="25.00"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 pr-4 text-sm font-semibold text-gray-900">Event</th>
                                    <th class="text-center py-3 px-4 text-sm font-semibold text-gray-900">Email</th>
                                    <th class="text-center py-3 px-4 text-sm font-semibold text-gray-900">Push</th>
                                    <th class="text-center py-3 px-4 text-sm font-semibold text-gray-900">In-App</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @php
                                    $eventLabels = [
                                        'outbid'         => ['Outbid Alert', 'When someone places a higher bid on an auction you bid on'],
                                        'auction_won'    => ['Auction Won', 'When you win an auction'],
                                        'auction_ending' => ['Auction Ending Soon', 'When a watched auction is about to end'],
                                        'wallet'         => ['Wallet Updates', 'Deposits, payments, and balance changes'],
                                        'marketing'      => ['Promotions & News', 'Featured auctions, platform news, and special offers'],
                                    ];
                                @endphp
                                @foreach($eventLabels as $event => [$label, $description])
                                    <tr>
                                        <td class="py-4 pr-4">
                                            <div class="font-medium text-gray-900 text-sm">{{ $label }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $description }}</div>
                                        </td>
                                        @foreach(['email', 'push', 'database'] as $channel)
                                            <td class="py-4 px-4 text-center">
                                                <input type="hidden" name="preferences[{{ $event }}][{{ $channel }}]" value="0">
                                                <input type="checkbox"
                                                       name="preferences[{{ $event }}][{{ $channel }}]"
                                                       value="1"
                                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                       @checked($preferences[$event][$channel] ?? false)>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                            Save Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
