@props([
    'auction',
    'binPrice',
    'available' => true,
])

@php
    $modalName = 'confirm-bin-purchase-' . $auction->id;
@endphp

<div
    x-data="binPurchase({
        auctionId: {{ $auction->id }},
        binPrice: {{ (float) $binPrice }},
        available: {{ $available ? 'true' : 'false' }},
        purchaseUrl: '{{ route('auctions.buy-it-now', $auction) }}',
        invoiceBaseUrl: '{{ url('/dashboard/invoices') }}',
        loginUrl: '{{ route('login') }}',
        modalName: '{{ $modalName }}',
    })"
    x-init="init()"
    x-show="available"
    x-cloak
    class="mt-4"
>
    <div class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Buy It Now</p>
                <p class="mt-1 text-sm text-amber-900">Skip the bidding and purchase instantly for {{ format_price((float) $binPrice) }}.</p>
            </div>
            <x-ui.badge color="amber" size="xs">BIN</x-ui.badge>
        </div>

        <button
            type="button"
            @click="confirm()"
            :disabled="loading"
            class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
        >
            <span x-show="!loading">Buy It Now for {{ format_price((float) $binPrice) }}</span>
            <span x-show="loading" x-cloak>Processing...</span>
        </button>
    </div>

    <x-modal :name="$modalName" max-width="md" focusable>
        <div class="p-6">
            <h2 id="{{ $modalName }}-title" class="text-lg font-semibold text-gray-900">
                Confirm instant purchase
            </h2>

            <p class="mt-3 text-sm text-gray-600">
                You are about to purchase this item for {{ format_price((float) $binPrice) }}. This action is final and will close the auction immediately.
            </p>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">
                    Cancel
                </x-secondary-button>
                <x-primary-button type="button" x-bind:disabled="loading" @click="purchase()">
                    <span x-show="!loading">Confirm Purchase</span>
                    <span x-show="loading" x-cloak>Processing...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
