<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Payment Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Stats --}}
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Active Escrow</div>
                    <div class="text-2xl font-bold text-amber-600">${{ number_format($stats['total_escrow'], 2) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Total Captured</div>
                    <div class="text-2xl font-bold text-green-600">${{ number_format($stats['total_captured'], 2) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Total Refunded</div>
                    <div class="text-2xl font-bold text-red-600">${{ number_format($stats['total_refunded'], 2) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Platform Revenue</div>
                    <div class="text-2xl font-bold text-indigo-600">${{ number_format($stats['total_revenue'], 2) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Pending Holds</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $stats['pending_payments'] }}</div>
                </div>
            </div>

            {{-- Active Escrow Holds --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Active Escrow Holds</h3>
                </div>
                @if($activeHolds->isEmpty())
                    <div class="p-8 text-center text-gray-400">No active holds.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Auction</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Since</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($activeHolds as $hold)
                                    <tr>
                                        <td class="px-6 py-4 text-sm">{{ $hold->user->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $hold->auction->title ?? "#{$hold->auction_id}" }}</td>
                                        <td class="px-6 py-4 text-sm text-right font-semibold text-amber-600">${{ number_format($hold->amount, 2) }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ $hold->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t">{{ $activeHolds->links() }}</div>
                @endif
            </div>

            {{-- Recent Invoices --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Invoices</h3>
                </div>
                @if($invoices->isEmpty())
                    <div class="p-8 text-center text-gray-400">No invoices yet.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Auction</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Buyer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Fee</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($invoices as $invoice)
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-mono">{{ $invoice->invoice_number }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $invoice->auction->title ?? "#{$invoice->auction_id}" }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $invoice->buyer->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $invoice->seller->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 text-sm text-right font-semibold">${{ number_format($invoice->total, 2) }}</td>
                                        <td class="px-6 py-4 text-sm text-right text-indigo-600">${{ number_format($invoice->platform_fee, 2) }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : '' }}
                                                {{ $invoice->status === 'refunded' ? 'bg-red-100 text-red-800' : '' }}">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t">{{ $invoices->links() }}</div>
                @endif
            </div>

            {{-- Recent Payments --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 border-b">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Payments</h3>
                        <a href="{{ route('admin.payments.transactions') }}" class="text-sm text-indigo-600 hover:underline">View all transactions</a>
                    </div>
                </div>
                @if($payments->isEmpty())
                    <div class="p-8 text-center text-gray-400">No payments yet.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($payments as $payment)
                                    <tr>
                                        <td class="px-6 py-4 text-sm">{{ $payment->user->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-700">{{ $payment->description ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-right font-semibold text-green-600">${{ number_format($payment->amount, 2) }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ $payment->created_at->format('M j, Y g:ia') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t">{{ $payments->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
