<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Auction</h2>
            <a href="{{ route('seller.auctions.import') }}" class="text-sm text-indigo-600 hover:text-indigo-700">Import from CSV -></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form id="auction-form" method="POST" action="{{ route('seller.auctions.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Basic Info</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <x-input-label for="title" value="Title" />
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('title')" />
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="categories" value="Categories (Select up to 3)" />
                                    <div x-data="categorySelect()">
                                        <select x-ref="select" id="categories" name="categories[]" multiple class="mt-1 block w-full" autocomplete="off">
                                            @foreach($categoryOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(in_array($value, old('categories', [])))>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <style>
                                        .ts-wrapper.multi.has-items .ts-control > input { display: none !important; }
                                        .ts-wrapper.multi .ts-control { min-height: 42px; display: flex; align-items: center; flex-wrap: wrap; gap: 4px; padding: 0.25rem 0.5rem; }
                                        .ts-wrapper .ts-dropdown .option.selected { opacity: 0.4; filter: blur(0.5px); pointer-events: none; }
                                    </style>
                                    <p class="text-xs text-gray-500 mt-1">First selected is primary.</p>
                                    <x-input-error class="mt-2" :messages="$errors->get('categories')" />
                                </div>
                                
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="condition" value="Condition" />
                                        <select id="condition" name="condition" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                            <option value="">Select Condition</option>
                                            @foreach($conditions as $value => $label)
                                                <option value="{{ $value }}" @selected(old('condition') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error class="mt-2" :messages="$errors->get('condition')" />
                                    </div>
                                    
                                    <div>
                                        <x-input-label for="brand_id" value="Brand (Optional)" />
                                        <select id="brand_id" name="brand_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                            <option value="">No Brand / Unlisted</option>
                                            @foreach($brands as $brand)
                                                <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->name }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error class="mt-2" :messages="$errors->get('brand_id')" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="sku" value="SKU (Optional)" />
                                    <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full" :value="old('sku')" />
                                    <x-input-error class="mt-2" :messages="$errors->get('sku')" />
                                </div>
                                <div>
                                    <x-input-label for="serial_number" value="Serial Number (Optional)" />
                                    <x-text-input id="serial_number" name="serial_number" type="text" class="mt-1 block w-full" :value="old('serial_number')" />
                                    <x-input-error class="mt-2" :messages="$errors->get('serial_number')" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="tags" value="Tags (Comma separated)" />
                                <x-text-input id="tags" name="tags" type="text" class="mt-1 block w-full" :value="old('tags')" placeholder="e.g. vintage, rare, electronics" />
                                <x-input-error class="mt-2" :messages="$errors->get('tags')" />
                            </div>

                            <div>
                                <x-input-label for="description" value="Description" />
                                <textarea id="description" name="description" rows="6" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>{{ old('description') }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('description')" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Pricing</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="starting_price" value="Starting Price" />
                                <x-text-input id="starting_price" name="starting_price" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('starting_price')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('starting_price')" />
                            </div>
                            <div>
                                <x-input-label for="reserve_price" value="Reserve Price (optional)" />
                                <x-text-input id="reserve_price" name="reserve_price" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('reserve_price')" />
                                <x-input-error class="mt-2" :messages="$errors->get('reserve_price')" />
                            </div>
                            <div>
                                <x-input-label for="min_bid_increment" value="Min Bid Increment" />
                                <x-text-input id="min_bid_increment" name="min_bid_increment" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('min_bid_increment', config('auction.min_bid_increment'))" />
                                <x-input-error class="mt-2" :messages="$errors->get('min_bid_increment')" />
                            </div>
                            <div>
                                <x-input-label for="currency" value="Currency" />
                                <select id="currency" name="currency" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach($supportedCurrencies as $currency)
                                        <option value="{{ $currency }}" @selected(old('currency', $defaultCurrency) === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('currency')" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Timing</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="start_time" value="Start Time (optional)" />
                                <x-text-input id="start_time" name="start_time" type="datetime-local" class="mt-1 block w-full" :value="old('start_time')" />
                                <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                            </div>
                            <div>
                                <x-input-label for="end_time" value="End Time" />
                                <x-text-input id="end_time" name="end_time" type="datetime-local" class="mt-1 block w-full" :value="old('end_time')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                            </div>
                        </div>
                    </div>

                    <details class="border border-gray-200 rounded-lg p-4">
                        <summary class="cursor-pointer font-medium text-gray-800">Advanced Anti-Snipe Settings</summary>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="snipe_threshold_seconds" value="Threshold (sec)" />
                                <x-text-input id="snipe_threshold_seconds" name="snipe_threshold_seconds" type="number" min="15" max="300" class="mt-1 block w-full" :value="old('snipe_threshold_seconds', config('auction.snipe.threshold_seconds'))" />
                            </div>
                            <div>
                                <x-input-label for="snipe_extension_seconds" value="Extension (sec)" />
                                <x-text-input id="snipe_extension_seconds" name="snipe_extension_seconds" type="number" min="15" max="300" class="mt-1 block w-full" :value="old('snipe_extension_seconds', config('auction.snipe.extension_seconds'))" />
                            </div>
                            <div>
                                <x-input-label for="max_extensions" value="Max Extensions" />
                                <x-text-input id="max_extensions" name="max_extensions" type="number" min="1" max="50" class="mt-1 block w-full" :value="old('max_extensions', config('auction.snipe.max_extensions'))" />
                            </div>
                        </div>
                    </details>

                    <div x-data="videoPreview()">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Video</h3>
                        <x-input-label for="video_url" value="YouTube/Vimeo URL (optional)" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input id="video_url" name="video_url" type="url" class="block w-full" x-model="url" @input="update" :value="old('video_url')" />
                            <button type="button" class="px-3 py-2 text-sm rounded border" @click="clear">Clear</button>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Paste a YouTube or Vimeo URL.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('video_url')" />

                        <template x-if="embedUrl">
                            <div class="mt-4 aspect-video rounded overflow-hidden border">
                                <iframe class="w-full h-full" :src="embedUrl" loading="lazy" allowfullscreen></iframe>
                            </div>
                        </template>
                        <p class="text-sm text-red-600 mt-2" x-show="url && !embedUrl">Invalid URL</p>
                    </div>

                    <div class="pt-4 border-t border-gray-200 flex items-center justify-between">
                        <p class="text-sm text-gray-500">Save as draft first. You can upload images on the next screen.</p>
                        <x-primary-button>Save as Draft</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const pad = n => String(n).padStart(2, '0');
            function utcToLocal(utcStr) {
                if (!utcStr) return '';
                const d = new Date(utcStr + 'Z');
                if (isNaN(d)) return utcStr;
                return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            }
            function localToUtc(localStr) {
                if (!localStr) return '';
                const d = new Date(localStr);
                if (isNaN(d)) return localStr;
                return `${d.getUTCFullYear()}-${pad(d.getUTCMonth()+1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`;
            }
            // On load: convert any old() UTC values to local for display
            document.querySelectorAll('#start_time, #end_time').forEach(input => {
                if (input.value) input.value = utcToLocal(input.value);
            });
            // On submit: convert local values to UTC
            const auctionForm = document.getElementById('auction-form');
            if (auctionForm) {
                auctionForm.addEventListener('submit', function() {
                    auctionForm.querySelectorAll('#start_time, #end_time').forEach(input => {
                        if (input.value) input.value = localToUtc(input.value);
                    });
                });
            }
        })();

        function categorySelect() {
            return {
                init() {
                    new window.TomSelect(this.$refs.select, {
                        plugins: ['remove_button', 'clear_button'],
                        maxItems: 3,
                        placeholder: 'Select categories...',
                        hidePlaceholder: true,
                        hideSelected: false,
                        onInitialize: function() {
                            this.rootMap = {};
                            let currentRoot = null;
                            const options = Array.from(this.input.options);
                            options.forEach(opt => {
                                const val = opt.value;
                                const text = opt.text;
                                if (!text.startsWith('—')) {
                                    currentRoot = val;
                                }
                                this.rootMap[val] = currentRoot || val;
                            });
                        },
                        onItemAdd: function(value) {
                            const root = this.rootMap[value];
                            const currentItems = [...this.items];
                            for (let item of currentItems) {
                                if (item !== value && this.rootMap[item] === root) {
                                    this.removeItem(item);
                                }
                            }
                        },
                        render: {
                            item: function(data, escape) {
                                const parts = data.text.split('—');
                                const text = parts.length > 1 ? parts[parts.length - 1].trim() : data.text;
                                return '<div>' + escape(text) + '</div>';
                            },
                            option: function(data, escape) {
                                const match = data.text.match(/^(—+)\s*(.*)/);
                                if (match) {
                                    const depth = match[1].length * 15;
                                    return '<div style=\"padding-left: ' + (depth + 8) + 'px\">' + escape(match[2]) + '</div>';
                                }
                                return '<div class=\"font-semibold bg-gray-50\">' + escape(data.text) + '</div>';
                            }
                        }
                    });
                }
            }
        }

        function videoPreview() {
            return {
                url: @js(old('video_url', '')),
                embedUrl: null,
                init() { this.update(); },
                clear() { this.url = ''; this.embedUrl = null; },
                update() {
                    const value = this.url.trim();
                    if (!value) {
                        this.embedUrl = null;
                        return;
                    }

                    const yt = value.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([A-Za-z0-9_-]{6,15})/i);
                    if (yt) {
                        this.embedUrl = `https://www.youtube.com/embed/${yt[1]}`;
                        return;
                    }

                    const vimeo = value.match(/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/i);
                    this.embedUrl = vimeo ? `https://player.vimeo.com/video/${vimeo[1]}` : null;
                }
            }
        }
    </script>
</x-app-layout>
