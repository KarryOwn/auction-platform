<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Invoice {{ $invoice->invoice_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(request('source') === 'bin')
                <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    Buy It Now purchase completed successfully.
                </div>
            @endif
            <div class="bg-white shadow-sm sm:rounded-lg p-8">
                {{-- Header --}}
                <div class="flex justify-between items-start mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">INVOICE</h1>
                        <p class="text-gray-500 mt-1">{{ $invoice->invoice_number }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-3 py-1 rounded text-sm font-medium
                            {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $invoice->status === 'refunded' ? 'bg-red-100 text-red-800' : '' }}
                            {{ $invoice->status === 'issued' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                            {{ strtoupper($invoice->status) }}
                        </span>
                    </div>
                </div>

                {{-- Parties --}}
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Buyer</h3>
                        <p class="text-gray-900 font-semibold">{{ $invoice->buyer->name }}</p>
                        <p class="text-gray-600 text-sm">{{ $invoice->buyer->email }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Seller</h3>
                        <p class="text-gray-900 font-semibold">{{ $invoice->seller->name }}</p>
                        <p class="text-gray-600 text-sm">{{ $invoice->seller->email }}</p>
                    </div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div>
                        <span class="text-sm text-gray-500">Issued:</span>
                        <span class="text-sm text-gray-900">{{ $invoice->issued_at?->format('F j, Y') }}</span>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">Paid:</span>
                        <span class="text-sm text-gray-900">{{ $invoice->paid_at?->format('F j, Y') ?? 'Pending' }}</span>
                    </div>
                </div>

                {{-- Auction Details --}}
                <div class="border-t border-b py-6 mb-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Auction Details</h3>
                    <div class="flex justify-between items-center">
                        <div>
                            <a href="{{ route('auctions.show', $invoice->auction_id) }}" class="text-indigo-600 hover:underline font-medium">
                                {{ $invoice->auction->title }}
                            </a>
                            <p class="text-sm text-gray-500">Auction #{{ $invoice->auction_id }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-semibold text-gray-900">${{ number_format($invoice->subtotal, 2) }}</p>
                            <p class="text-xs text-gray-500">{{ $invoice->currency }}</p>
                        </div>
                    </div>
                </div>

                {{-- Breakdown --}}
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal (Winning Bid)</span>
                        <span class="text-gray-900">${{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Platform Fee ({{ number_format((float) ($invoice->commission_rate_percent ?? ((float) config('auction.platform_fee_percent', 0.05) * 100)), 2) }}%)</span>
                        <span class="text-gray-900">${{ number_format($invoice->platform_fee, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Seller Payout</span>
                        <span class="text-gray-900">${{ number_format($invoice->seller_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold border-t pt-3">
                        <span>Total Charged to Buyer</span>
                        <span>${{ number_format($invoice->total, 2) }}</span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3">
                    <a href="{{ route('user.invoices') }}"
                       class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
                        Back to Invoices
                    </a>
                    <a href="{{ route('user.invoices.download', $invoice) }}"
                       class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                        Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
