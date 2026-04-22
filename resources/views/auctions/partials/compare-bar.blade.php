<div x-data="auctionCompareBar()"
     x-init="init()"
     x-cloak
     x-show="ids.length > 0"
     class="fixed inset-x-0 bottom-4 z-[90] px-4">
    <div class="mx-auto max-w-5xl rounded-2xl border border-gray-900/10 bg-gray-950 text-white shadow-2xl shadow-gray-900/25">
        <div class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-center md:justify-between">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-amber-400 text-sm font-bold text-gray-900" x-text="ids.length"></span>
                    <div>
                        <p class="text-sm font-semibold tracking-wide text-white">Comparison tray</p>
                        <p class="text-xs text-gray-300">Choose up to 4 active auctions and open the side-by-side table.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="id in ids" :key="id">
                        <button type="button"
                                class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/20"
                                @click="remove(id)">
                            <span x-text="'Auction #' + id"></span>
                            <span class="text-amber-300">×</span>
                        </button>
                    </template>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="button"
                        class="inline-flex items-center justify-center min-h-11 rounded-lg border border-white/20 px-4 py-2 text-sm font-medium text-white hover:bg-white/10"
                        @click="clear()">
                    Clear
                </button>
                <button type="button"
                        class="inline-flex items-center justify-center min-h-11 rounded-lg bg-amber-400 px-4 py-2 text-sm font-bold text-gray-900 hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="ids.length < 2"
                        @click="openCompare()">
                    Compare Selected
                </button>
            </div>
        </div>
    </div>
</div>
