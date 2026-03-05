<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Invoices') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                @if($invoices->isEmpty())
                    <div class="p-12 text-center">
                        <p class="text-gray-400 text-lg">No invoices yet.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Auction</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($invoices as $invoice)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                            {{ $invoice->invoice_number }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            <a href="{{ route('auctions.show', $invoice->auction_id) }}" class="text-indigo-600 hover:underline">
                                                {{ $invoice->auction->title ?? "Auction #{$invoice->auction_id}" }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($invoice->buyer_id === $user->id)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Buyer</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">Seller</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right text-gray-900">
                                            ${{ number_format($invoice->total, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : '' }}
                                                {{ $invoice->status === 'refunded' ? 'bg-red-100 text-red-800' : '' }}
                                                {{ $invoice->status === 'issued' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $invoice->issued_at?->format('M j, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right space-x-2">
                                            <a href="{{ route('user.invoices.show', $invoice) }}" class="text-indigo-600 hover:underline">View</a>
                                            <a href="{{ route('user.invoices.download', $invoice) }}" class="text-indigo-600 hover:underline">PDF</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
