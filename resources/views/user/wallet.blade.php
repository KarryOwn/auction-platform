<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Wallet') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Success Message --}}
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-green-800 text-sm">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Balance & Top-Up --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="space-y-2">
                        <div>
                            <div class="text-sm font-medium text-gray-500">Total Balance</div>
                            <div class="text-4xl font-bold text-green-600 mt-1">${{ number_format($user->wallet_balance, 2) }}</div>
                        </div>
                        <div class="flex gap-6 text-sm">
                            <div>
                                <span class="text-gray-500">Available:</span>
                                <span class="font-semibold text-gray-800">${{ number_format($user->availableBalance(), 2) }}</span>
                            </div>
                            @if((float) $user->held_balance > 0)
                                <div>
                                    <span class="text-gray-500">Held in Escrow:</span>
                                    <span class="font-semibold text-amber-600">${{ number_format($user->held_balance, 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <form method="POST" action="{{ route('user.wallet.stripe.checkout') }}" class="flex items-end gap-3">
                            @csrf
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Top-Up Amount</label>
                                <div class="flex gap-2 mb-2">
                                    @foreach([50, 100, 250, 500] as $preset)
                                        <button type="button"
                                                onclick="document.getElementById('amount').value = {{ $preset }}"
                                                class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm font-medium text-gray-700 transition">
                                            ${{ $preset }}
                                        </button>
                                    @endforeach
                                </div>
                                <input type="number" name="amount" id="amount" min="1" max="50000" step="0.01"
                                       placeholder="Custom amount"
                                       value="{{ old('amount') }}"
                                       class="rounded-md border-gray-300 shadow-sm text-sm w-40">
                                @error('amount')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit"
                                    class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition flex items-center gap-2">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                </svg>
                                Pay with Stripe
                            </button>
                        </form>

                        <form method="POST" action="{{ route('user.wallet.withdraw') }}" class="flex items-end gap-3">
                            @csrf
                            <div>
                                <label for="withdraw_amount" class="block text-sm font-medium text-gray-700 mb-1">Withdraw Amount</label>
                                <input type="number" name="amount" id="withdraw_amount" min="1" max="50000" step="0.01"
                                       placeholder="Amount to withdraw"
                                       class="rounded-md border-gray-300 shadow-sm text-sm w-40">
                            </div>
                            <button type="submit" class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition">
                                Withdraw
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Active Escrow Holds --}}
            @if($activeHolds->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Active Escrow Holds</h3>
                    <div class="space-y-3">
                        @foreach($activeHolds as $hold)
                            <div class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-900">{{ $hold->auction->title ?? "Auction #{$hold->auction_id}" }}</span>
                                </div>
                                <span class="text-sm font-semibold text-amber-700">${{ number_format($hold->amount, 2) }} held</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Transaction History --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 border-b">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Transaction History</h3>
                        <a href="{{ route('user.wallet.export') }}" class="text-sm text-indigo-600 hover:underline">Export CSV</a>
                    </div>

                    {{-- Filters --}}
                    <form method="GET" action="{{ route('user.wallet') }}" class="flex flex-wrap items-end gap-4 mt-4">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="type" id="type" class="rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">All</option>
                                <option value="deposit" @selected(request('type') === 'deposit')>Deposits</option>
                                <option value="payment" @selected(request('type') === 'payment')>Payments</option>
                                <option value="bid_hold" @selected(request('type') === 'bid_hold')>Bid Holds</option>
                                <option value="bid_release" @selected(request('type') === 'bid_release')>Bid Releases</option>
                                <option value="withdrawal" @selected(request('type') === 'withdrawal')>Withdrawals</option>
                                <option value="refund" @selected(request('type') === 'refund')>Refunds</option>
                                <option value="seller_credit" @selected(request('type') === 'seller_credit')>Seller Credits</option>
                            </select>
                        </div>
                        <div>
                            <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                            <input type="date" name="from" id="from" value="{{ request('from') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <div>
                            <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                            <input type="date" name="to" id="to" value="{{ request('to') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">Filter</button>
                    </form>
                </div>

                @if($transactions->isEmpty())
                    <div class="p-12 text-center">
                        <p class="text-gray-400 text-lg">No transactions yet.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($transactions as $tx)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tx->created_at->format('M j, Y g:ia') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                {{ $tx->isCredit() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ ucfirst(str_replace('_', ' ', $tx->type)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">{{ $tx->description ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right {{ $tx->isCredit() ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $tx->isCredit() ? '+' : '-' }}${{ number_format(abs((float) $tx->amount), 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right">
                                            ${{ number_format((float) $tx->balance_after, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
