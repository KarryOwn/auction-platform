<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('All Transactions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Filters --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('admin.payments.transactions') }}" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="rounded-md border-gray-300 text-sm">
                            <option value="">All Types</option>
                            <option value="deposit" @selected(request('type') === 'deposit')>Deposit</option>
                            <option value="withdrawal" @selected(request('type') === 'withdrawal')>Withdrawal</option>
                            <option value="bid_hold" @selected(request('type') === 'bid_hold')>Bid Hold</option>
                            <option value="bid_release" @selected(request('type') === 'bid_release')>Hold Release</option>
                            <option value="purchase" @selected(request('type') === 'purchase')>Purchase</option>
                            <option value="seller_credit" @selected(request('type') === 'seller_credit')>Seller Credit</option>
                            <option value="refund" @selected(request('type') === 'refund')>Refund</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                        <input type="text" name="user" value="{{ request('user') }}" placeholder="Name or ID"
                               class="rounded-md border-gray-300 text-sm w-40" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                            Filter
                        </button>
                        <a href="{{ route('admin.payments.transactions') }}" class="ml-2 text-sm text-gray-500 hover:underline">Reset</a>
                    </div>
                </form>
            </div>

            {{-- Transactions Table --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                @if($transactions->isEmpty())
                    <div class="p-8 text-center text-gray-400">No transactions found.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance After</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($transactions as $tx)
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-gray-400">#{{ $tx->id }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $tx->user->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                {{ in_array($tx->type, ['deposit', 'refund', 'seller_credit', 'bid_release']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ str_replace('_', ' ', ucfirst($tx->type)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">{{ $tx->description ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-right font-semibold
                                            {{ $tx->isCredit() ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $tx->isCredit() ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-right text-gray-500">${{ number_format($tx->balance_after, 2) }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ $tx->created_at->format('M j, Y g:ia') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
