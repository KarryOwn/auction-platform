<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Process Refund') }} — {{ $auction->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">

                {{-- Auction Summary --}}
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Auction</span>
                        <p class="font-semibold">{{ $auction->title }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Status</span>
                        <p class="font-semibold capitalize">{{ $auction->status }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Winning Bid</span>
                        <p class="font-semibold">${{ number_format($auction->current_price, 2) }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Winner</span>
                        <p class="font-semibold">{{ $auction->winner?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Seller</span>
                        <p class="font-semibold">{{ $auction->seller?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Payment Status</span>
                        <p class="font-semibold capitalize">{{ $auction->payment_status ?? 'none' }}</p>
                    </div>
                </div>

                @if($auction->invoice)
                    <div class="border-t pt-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Invoice</span>
                            <p class="font-semibold font-mono">{{ $auction->invoice->invoice_number }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Invoice Status</span>
                            <p class="font-semibold capitalize">{{ $auction->invoice->status }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Total</span>
                            <p class="font-semibold">${{ number_format($auction->invoice->total, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Platform Fee</span>
                            <p class="font-semibold text-indigo-600">${{ number_format($auction->invoice->platform_fee, 2) }}</p>
                        </div>
                    </div>
                @endif

                @if($auction->payment_status === 'captured')
                    <form method="POST" action="{{ route('admin.auctions.refund.process', $auction) }}" class="border-t pt-6 space-y-4">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Refund Type</label>
                            <select name="type" id="refund-type" class="rounded-md border-gray-300 w-full"
                                    onchange="document.getElementById('partial-amount').classList.toggle('hidden', this.value !== 'partial')">
                                <option value="full">Full Refund (${{ number_format($auction->current_price, 2) }})</option>
                                <option value="partial">Partial Refund</option>
                            </select>
                        </div>

                        <div id="partial-amount" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $auction->current_price }}"
                                   class="rounded-md border-gray-300 w-full" placeholder="0.00" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                            <textarea name="reason" rows="3" class="rounded-md border-gray-300 w-full"
                                      placeholder="Reason for the refund..."></textarea>
                        </div>

                        @if($errors->any())
                            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                                @foreach($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-center gap-3">
                            <button type="submit"
                                    onclick="return confirm('Are you sure you want to process this refund? This action cannot be undone.')"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">
                                Process Refund
                            </button>
                            <a href="{{ route('admin.payments.index') }}" class="text-sm text-gray-500 hover:underline">Cancel</a>
                        </div>
                    </form>
                @elseif($auction->payment_status === 'refunded')
                    <div class="border-t pt-4">
                        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded text-sm">
                            This auction has already been refunded.
                        </div>
                    </div>
                @else
                    <div class="border-t pt-4">
                        <div class="bg-gray-50 border border-gray-200 text-gray-600 px-4 py-3 rounded text-sm">
                            Refund is only available for auctions with captured payments.
                            Current payment status: <strong>{{ $auction->payment_status ?? 'none' }}</strong>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
