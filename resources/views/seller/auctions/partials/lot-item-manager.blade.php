@php
    $lotItemSeed = old('lot_items');

    if ($lotItemSeed === null && isset($auction)) {
        $lotItemSeed = $auction->lotItems->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'condition' => $item->condition,
                'description' => $item->description,
                'image_url' => $item->getFirstMediaUrl('image', 'thumbnail') ?: $item->getFirstMediaUrl('image'),
            ];
        })->values()->all();
    }

    $lotItemSeed = collect($lotItemSeed ?? [])->map(function ($item) {
        return [
            'id' => $item['id'] ?? null,
            'name' => $item['name'] ?? '',
            'quantity' => $item['quantity'] ?? 1,
            'condition' => $item['condition'] ?? '',
            'description' => $item['description'] ?? '',
            'image_url' => $item['image_url'] ?? null,
        ];
    })->values()->all();
@endphp

<div x-data="lotItemManager({
        initialIsLot: {{ old('is_lot', isset($auction) ? (int) $auction->is_lot : 0) ? 'true' : 'false' }},
        initialItems: @js($lotItemSeed),
    })"
    class="rounded-2xl border border-violet-200 bg-violet-50/50 p-5">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-violet-600">Lot auction</p>
            <h3 class="mt-2 text-lg font-semibold text-slate-950">Bundle multiple items into one listing</h3>
            <p class="mt-1 text-sm text-slate-600">Add every item included in this auction so buyers know exactly what ships.</p>
        </div>
        <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-violet-700 ring-1 ring-violet-200" x-text="`${items.length} / 50 items`"></div>
    </div>

    <label class="mt-5 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 ring-1 ring-violet-200">
        <input type="checkbox"
               name="is_lot"
               value="1"
               x-model="isLot"
               class="rounded border-violet-300 text-violet-600 focus:ring-violet-500">
        <span class="text-sm font-medium text-slate-800">This is a lot auction</span>
    </label>

    <div x-show="isLot" x-cloak x-transition class="mt-5 space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-600">Add at least one lot item before publishing.</p>
            <button type="button"
                    @click="addItem()"
                    x-bind:disabled="items.length >= 50"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60">
                + Add Item
            </button>
        </div>

        <div x-show="items.length === 0" x-cloak class="rounded-2xl border border-dashed border-violet-300 bg-white px-5 py-8 text-center text-sm text-slate-500">
            No items added yet. Add at least one item.
        </div>

        <template x-for="(item, index) in items" :key="item.key">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex items-center gap-3">
                        <button type="button" @click="moveUp(index)" class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-50" :disabled="index === 0">
                            <span class="sr-only">Move item up</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                        </button>
                        <button type="button" @click="moveDown(index)" class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-50" :disabled="index === items.length - 1">
                            <span class="sr-only">Move item down</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400" x-text="`Item ${index + 1}`"></p>
                            <p class="text-sm text-slate-500">Describe what is included in the bundle.</p>
                        </div>
                    </div>
                    <button type="button" @click="removeItem(index)" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50">
                        Remove
                    </button>
                </div>

                <input type="hidden" x-bind:name="`lot_items[${index}][id]`" x-model="item.id">

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Item name</label>
                        <input type="text"
                               x-bind:name="`lot_items[${index}][name]`"
                               x-model="item.name"
                               class="mt-2 w-full rounded-2xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500"
                               placeholder="Vintage lens, charger, manuals"
                               required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Quantity</label>
                        <input type="number"
                               min="1"
                               max="999"
                               x-bind:name="`lot_items[${index}][quantity]`"
                               x-model="item.quantity"
                               class="mt-2 w-full rounded-2xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500"
                               required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Condition</label>
                        <input type="text"
                               x-bind:name="`lot_items[${index}][condition]`"
                               x-model="item.condition"
                               class="mt-2 w-full rounded-2xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500"
                               placeholder="Used - Good">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Item image</label>
                        <input type="file"
                               accept="image/png,image/jpeg,image/webp"
                               x-bind:name="`lot_items[${index}][image]`"
                               @change="previewImage($event, index)"
                               class="mt-2 block w-full rounded-2xl border border-gray-300 bg-white px-3 py-2 text-sm text-slate-600 shadow-sm">
                        <template x-if="item.image_url">
                            <img :src="item.image_url" alt="" class="mt-3 h-24 w-24 rounded-2xl object-cover ring-1 ring-slate-200">
                        </template>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700">Description</label>
                    <textarea x-bind:name="`lot_items[${index}][description]`"
                              x-model="item.description"
                              rows="3"
                              class="mt-2 w-full rounded-2xl border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500"
                              placeholder="Include dimensions, included accessories, and notable wear."></textarea>
                </div>
            </div>
        </template>

        <x-input-error class="mt-2" :messages="$errors->get('lot_items')" />
    </div>
</div>

@once
    @push('scripts')
        <script>
            function lotItemManager(config) {
                return {
                    isLot: Boolean(config.initialIsLot),
                    items: (config.initialItems || []).map((item, index) => ({
                        key: item.id ?? `new-${index}-${Date.now()}`,
                        id: item.id ?? '',
                        name: item.name ?? '',
                        quantity: item.quantity ?? 1,
                        condition: item.condition ?? '',
                        description: item.description ?? '',
                        image_url: item.image_url ?? null,
                    })),
                    addItem() {
                        if (this.items.length >= 50) {
                            return;
                        }

                        this.items.push({
                            key: `new-${Date.now()}-${Math.random()}`,
                            id: '',
                            name: '',
                            quantity: 1,
                            condition: '',
                            description: '',
                            image_url: null,
                        });
                    },
                    removeItem(index) {
                        this.items.splice(index, 1);
                    },
                    moveUp(index) {
                        if (index === 0) {
                            return;
                        }

                        const item = this.items.splice(index, 1)[0];
                        this.items.splice(index - 1, 0, item);
                    },
                    moveDown(index) {
                        if (index >= this.items.length - 1) {
                            return;
                        }

                        const item = this.items.splice(index, 1)[0];
                        this.items.splice(index + 1, 0, item);
                    },
                    previewImage(event, index) {
                        const file = event.target.files?.[0];
                        if (!file) {
                            return;
                        }

                        this.items[index].image_url = URL.createObjectURL(file);
                    },
                };
            }
        </script>
    @endpush
@endonce
