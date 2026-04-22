<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Auction</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('status'))
                <div class="bg-green-100 text-green-800 px-4 py-3 rounded">{{ session('status') }}</div>
            @endif

            @if($errors->has('auction'))
                <div class="bg-red-100 text-red-800 px-4 py-3 rounded">{{ $errors->first('auction') }}</div>
            @endif

            @if($auction->isDraft() && $auction->cloned_from_auction_id)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                    <p class="font-semibold">Relisted draft</p>
                    <p class="mt-1">This draft was created from a previous auction. Review dates, pricing, inventory, and shipping details before publishing.</p>
                    @if(!$auction->end_time)
                        <p class="mt-2 font-medium">No end date is set yet. Configure the schedule before publishing.</p>
                    @endif
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="auctionEdit({
                processUrl: '{{ route('seller.auctions.images.upload', $auction) }}',
                deleteTemplate: '{{ route('seller.auctions.images.delete', [$auction, 'media' => '__MEDIA_ID__']) }}',
                reorderUrl: '{{ route('seller.auctions.images.reorder', $auction) }}',
                initialFiles: @js($auction->getMedia('images')->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'thumbnail_url' => $m->getUrl('thumbnail'),
                    'mime_type' => $m->mime_type,
                    'size' => $m->size,
                ])->values()),
                maxFiles: {{ $imageMaxCount }},
                maxFileSize: '{{ $imageMaxSizeMb }}MB',
                acceptedTypes: @js($acceptedTypes),
            })" x-init="init()">
                <form id="auction-form"
                      method="POST"
                      action="{{ route('seller.auctions.update', $auction) }}"
                      enctype="multipart/form-data"
                      class="space-y-6"
                      x-data="autoSave({ url: '{{ route('seller.auctions.auto-save', $auction) }}' })"
                      x-init="init()">
                    @csrf
                    @method('PATCH')

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Draft editor</p>
                            <p class="text-xs" :class="statusClass()" x-text="statusLabel()"></p>
                        </div>
                        @if($auction->isDraft())
                            <a href="{{ route('seller.auctions.preview', $auction) }}"
                               class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-md border border-amber-300 bg-white text-sm font-medium text-amber-900 hover:bg-amber-50">
                                Preview
                            </a>
                        @endif
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Images</h3>
                        <input type="file" name="file" class="filepond" x-ref="pondInput" multiple>
                        <p class="mt-2 text-sm text-gray-500">Drag and drop images to upload, reorder, and remove.</p>
                    </div>

                    @include('seller.auctions.partials.lot-item-manager', ['auction' => $auction])

                    @php($authCertMedia = $auction->getFirstMedia('authenticity_cert'))
                    @php($authCertDownloadUrl = $authCertMedia && in_array($auction->status, [\App\Models\Auction::STATUS_ACTIVE, \App\Models\Auction::STATUS_COMPLETED], true) ? route('auctions.auth-cert.download', $auction) : '')
                    <div class="border border-gray-200 rounded-lg p-4"
                         x-data="authCertManager({
                             uploadUrl: '{{ route('seller.auctions.auth-cert.upload', $auction) }}',
                             deleteUrl: '{{ route('seller.auctions.auth-cert.delete', $auction) }}',
                             downloadUrl: '{{ $authCertDownloadUrl }}',
                             csrf: '{{ csrf_token() }}',
                             initialStatus: @js($auction->authenticity_cert_status),
                             initialHasFile: @js((bool) $authCertMedia),
                             initialFileName: @js($authCertMedia?->file_name),
                             initialNotes: @js($auction->authenticity_cert_notes),
                         })">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Authenticity Certificate</h3>
                                <p class="mt-1 text-sm text-gray-500">Upload one PDF or image up to 10 MB for staff review.</p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                  :class="badgeClass"
                                  x-text="badgeText"></span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <input type="file" x-ref="fileInput" accept=".pdf,image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-700">

                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60" :disabled="submitting" @click="upload">
                                    Upload Certificate
                                </button>
                                <a x-show="hasFile && downloadUrl" :href="downloadUrl" class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-md border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    View Current Certificate
                                </a>
                                <button x-show="hasFile" type="button" class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-md border border-red-300 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60" :disabled="submitting" @click="remove">
                                    Delete Certificate
                                </button>
                            </div>

                            <template x-if="fileName">
                                <p class="text-sm text-gray-600">
                                    Current file: <span class="font-medium" x-text="fileName"></span>
                                </p>
                            </template>

                            <template x-if="notes">
                                <p class="text-sm text-gray-600">
                                    Review notes: <span class="font-medium" x-text="notes"></span>
                                </p>
                            </template>

                            <p class="text-sm" :class="messageClass" x-show="message" x-text="message"></p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Basic Info</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <x-input-label for="title" value="Title" />
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $auction->title)" data-autosave />
                                <x-input-error class="mt-2" :messages="$errors->get('title')" />
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="categories" value="Categories (Select up to 3)" />
                                    <div x-data="categorySelect()">
                                        <select x-ref="select" id="categories" name="categories[]" multiple class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" autocomplete="off">
                                            @php
                                                $selectedCategories = old('categories', $auction->categories->pluck('id')->toArray());
                                            @endphp
                                            @foreach($categoryOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(in_array($value, $selectedCategories))>{{ $label }}</option>
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
                                        <select id="condition" name="condition" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                            <option value="">Select Condition</option>
                                            @foreach($conditions as $value => $label)
                                                <option value="{{ $value }}" @selected(old('condition', $auction->condition) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error class="mt-2" :messages="$errors->get('condition')" />
                                    </div>
                                    
                                    <div>
                                        <x-input-label for="brand_id" value="Brand (Optional)" />
                                        <select id="brand_id" name="brand_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                            <option value="">No Brand / Unlisted</option>
                                            @foreach($brands as $brand)
                                                <option value="{{ $brand->id }}" @selected(old('brand_id', $auction->brand_id) == $brand->id)>{{ $brand->name }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error class="mt-2" :messages="$errors->get('brand_id')" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="sku" value="SKU (Optional)" />
                                    <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full" :value="old('sku', $auction->sku)" />
                                    <x-input-error class="mt-2" :messages="$errors->get('sku')" />
                                </div>
                                <div>
                                    <x-input-label for="serial_number" value="Serial Number (Optional)" />
                                    <x-text-input id="serial_number" name="serial_number" type="text" class="mt-1 block w-full" :value="old('serial_number', $auction->serial_number)" />
                                    <x-input-error class="mt-2" :messages="$errors->get('serial_number')" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="tags" value="Tags (Comma separated)" />
                                <x-text-input id="tags" name="tags" type="text" class="mt-1 block w-full" :value="old('tags', $auction->tags->pluck('name')->implode(', '))" placeholder="e.g. vintage, rare, electronics" />
                                <x-input-error class="mt-2" :messages="$errors->get('tags')" />
                            </div>

                            <div>
                                <x-input-label for="description" value="Description" />
                                <textarea id="description" name="description" rows="6" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" data-autosave>{{ old('description', $auction->description) }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('description')" />
                            </div>
                        </div>
                    </div>

                    <div x-data="{ buyItNowEnabled: {{ old('buy_it_now_enabled', $auction->buy_it_now_enabled) ? 'true' : 'false' }} }">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Pricing</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="starting_price" value="Starting Price" />
                                <x-text-input id="starting_price" name="starting_price" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('starting_price', $auction->starting_price)" />
                                <x-input-error class="mt-2" :messages="$errors->get('starting_price')" />
                            </div>
                            <div>
                                <x-input-label for="reserve_price" value="Reserve Price" />
                                <x-text-input id="reserve_price" name="reserve_price" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('reserve_price', $auction->reserve_price)" />
                                <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox"
                                           name="reserve_price_visible"
                                           value="1"
                                           @checked(old('reserve_price_visible', $auction->reserve_price_visible))
                                           data-autosave
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>Show reserve price to bidders</span>
                                </label>
                                <x-input-error class="mt-2" :messages="$errors->get('reserve_price')" />
                            </div>
                            <div>
                                <x-input-label for="min_bid_increment" value="Min Bid Increment" />
                                <x-text-input id="min_bid_increment" name="min_bid_increment" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('min_bid_increment', $auction->min_bid_increment)" />
                                <x-input-error class="mt-2" :messages="$errors->get('min_bid_increment')" />
                            </div>
                            <div>
                                <x-input-label for="currency" value="Currency" />
                                <select id="currency" name="currency" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach($supportedCurrencies as $currency)
                                        <option value="{{ $currency }}" @selected(old('currency', $auction->currency) === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('currency')" />
                            </div>
                        </div>
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <label class="flex items-center gap-2 text-sm font-medium text-amber-900">
                                <input type="checkbox"
                                       name="buy_it_now_enabled"
                                       value="1"
                                       x-model="buyItNowEnabled"
                                       class="rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                                <span>Enable Buy It Now</span>
                            </label>
                            <p class="mt-2 text-xs text-amber-800">The instant-purchase option automatically disappears once bidding reaches 75% of the BIN price.</p>
                            <div x-show="buyItNowEnabled" x-cloak class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label for="buy_it_now_price" value="Buy It Now Price" />
                                    <x-text-input id="buy_it_now_price" name="buy_it_now_price" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('buy_it_now_price', $auction->buy_it_now_price)" />
                                    <x-input-error class="mt-2" :messages="$errors->get('buy_it_now_price')" />
                                </div>
                                <div>
                                    <x-input-label for="buy_it_now_expires_at" value="BIN expiry (optional)" />
                                    <x-text-input id="buy_it_now_expires_at" name="buy_it_now_expires_at" type="datetime-local" class="mt-1 block w-full" :value="old('buy_it_now_expires_at', optional($auction->buy_it_now_expires_at)->format('Y-m-d\\TH:i'))" />
                                    <x-input-error class="mt-2" :messages="$errors->get('buy_it_now_expires_at')" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Timing</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="start_time" value="Start Time" />
                                <x-text-input id="start_time" name="start_time" type="datetime-local" class="mt-1 block w-full" :value="old('start_time', optional($auction->start_time)->format('Y-m-d\\TH:i'))" />
                                <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                            </div>
                            <div>
                                <x-input-label for="end_time" value="End Time" />
                                <x-text-input id="end_time" name="end_time" type="datetime-local" class="mt-1 block w-full" :value="old('end_time', optional($auction->end_time)->format('Y-m-d\\TH:i'))" />
                                <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                            </div>
                        </div>
                    </div>

                    <details class="border border-gray-200 rounded-lg p-4">
                        <summary class="cursor-pointer font-medium text-gray-800">Advanced Anti-Snipe Settings</summary>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="snipe_threshold_seconds" value="Threshold (sec)" />
                                <x-text-input id="snipe_threshold_seconds" name="snipe_threshold_seconds" type="number" min="15" max="300" class="mt-1 block w-full" :value="old('snipe_threshold_seconds', $auction->snipe_threshold_seconds)" />
                            </div>
                            <div>
                                <x-input-label for="snipe_extension_seconds" value="Extension (sec)" />
                                <x-text-input id="snipe_extension_seconds" name="snipe_extension_seconds" type="number" min="15" max="300" class="mt-1 block w-full" :value="old('snipe_extension_seconds', $auction->snipe_extension_seconds)" />
                            </div>
                            <div>
                                <x-input-label for="max_extensions" value="Max Extensions" />
                                <x-text-input id="max_extensions" name="max_extensions" type="number" min="1" max="50" class="mt-1 block w-full" :value="old('max_extensions', $auction->max_extensions)" />
                            </div>
                        </div>
                    </details>

                    <div x-data="videoPreview('{{ old('video_url', $auction->video_url) }}')" x-init="init()">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Video</h3>
                        <x-input-label for="video_url" value="YouTube/Vimeo URL" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input id="video_url" name="video_url" type="url" class="block w-full" x-model="url" @input="update" data-autosave />
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

                    <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200">
                        <x-primary-button>
                            {{ $auction->isDraft() ? 'Save Draft' : 'Save Changes' }}
                        </x-primary-button>
                    </div>
                </form>

                <div class="flex flex-wrap gap-3 pt-4 mt-4 border-t border-gray-200">
                    @if($auction->isDraft())
                        <div x-data="listingFeePublish({
                            previewUrl: '{{ route('seller.auctions.listing-fee-preview', $auction) }}',
                            walletBalance: {{ (float) auth()->user()->availableBalance() }},
                            modalName: 'confirm-publish-{{ $auction->id }}',
                        })">
                            <form method="POST" action="{{ route('seller.auctions.publish', $auction) }}" x-ref="publishForm">
                                @csrf
                                <x-secondary-button type="button" @click="preview()" x-bind:disabled="loading">
                                    <span x-show="!loading">Publish Auction</span>
                                    <span x-show="loading" x-cloak>Checking fee...</span>
                                </x-secondary-button>
                            </form>

                            <x-modal name="confirm-publish-{{ $auction->id }}" max-width="md" focusable>
                                <div class="p-6">
                                    <h2 id="confirm-publish-{{ $auction->id }}-title" class="text-lg font-semibold text-gray-900">
                                        Confirm auction publish
                                    </h2>
                                    <p class="mt-3 text-sm text-gray-600">
                                        Publishing this auction will deduct a listing fee from your wallet.
                                    </p>

                                    <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 space-y-2">
                                        <div class="flex items-center justify-between gap-4">
                                            <span>Listing fee</span>
                                            <span class="font-semibold">$<span x-text="fee.toFixed(2)"></span></span>
                                        </div>
                                        <div class="flex items-center justify-between gap-4">
                                            <span>Available wallet balance</span>
                                            <span class="font-semibold">$<span x-text="walletBalance.toFixed(2)"></span></span>
                                        </div>
                                    </div>

                                    <p x-show="!canAfford()" x-cloak class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                        Insufficient wallet balance. Add funds before publishing this auction.
                                    </p>

                                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                        <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'confirm-publish-{{ $auction->id }}')">
                                            Cancel
                                        </x-secondary-button>
                                        <a x-show="!canAfford()" x-cloak href="{{ route('user.wallet') }}"
                                           class="inline-flex items-center justify-center rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                                            Top Up Wallet
                                        </a>
                                        <x-primary-button type="button" x-show="canAfford()" @click="confirmPublish()">
                                            Confirm Publish
                                        </x-primary-button>
                                    </div>
                                </div>
                            </x-modal>
                        </div>

                        <form method="POST" action="{{ route('seller.auctions.destroy', $auction) }}" onsubmit="return confirm('Delete this draft?')">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>Delete Draft</x-danger-button>
                        </form>
                    @endif

                    @if($auction->status === \App\Models\Auction::STATUS_ACTIVE && (int) $auction->bid_count === 0)
                        <form method="POST" action="{{ route('seller.auctions.cancel', $auction) }}" onsubmit="return confirm('Cancel this auction?')">
                            @csrf
                            <x-danger-button>Cancel Auction</x-danger-button>
                        </form>
                    @endif
                </div>
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
            // On load: convert UTC model values to local for display
            document.querySelectorAll('#start_time, #end_time, #buy_it_now_expires_at').forEach(input => {
                if (input.value) input.value = utcToLocal(input.value);
            });
            // On submit: convert local values to UTC
            const auctionForm = document.getElementById('auction-form');
            if (auctionForm) {
                auctionForm.addEventListener('submit', function() {
                    auctionForm.querySelectorAll('#start_time, #end_time, #buy_it_now_expires_at').forEach(input => {
                        if (input.value) input.value = localToUtc(input.value);
                    });
                });
            }
        })();

        window.categorySelect = function() {
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

        window.auctionEdit = function (options) {
            return {
                pond: null,
                init() {
                    this.pond = initAuctionFilepond(this.$refs.pondInput, {
                        processUrl: options.processUrl,
                        deleteUrlTemplate: options.deleteTemplate,
                        reorderUrl: options.reorderUrl,
                        initialFiles: options.initialFiles,
                        maxFiles: options.maxFiles,
                        maxFileSize: options.maxFileSize,
                        acceptedFileTypes: options.acceptedTypes,
                    });
                }
            }
        }

        window.videoPreview = function (initial = '') {
            return {
                url: initial || '',
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

        window.authCertManager = function (config) {
            return {
                status: config.initialStatus || 'none',
                hasFile: Boolean(config.initialHasFile),
                fileName: config.initialFileName || '',
                notes: config.initialNotes || '',
                downloadUrl: config.downloadUrl || '',
                submitting: false,
                message: '',
                messageClass: 'text-gray-600',
                get badgeText() {
                    return {
                        none: 'No certificate',
                        uploaded: 'Pending verification',
                        verified: 'Verified ✓',
                        rejected: 'Rejected ✗',
                    }[this.status] || this.status;
                },
                get badgeClass() {
                    return {
                        none: 'bg-gray-100 text-gray-700',
                        uploaded: 'bg-amber-100 text-amber-800',
                        verified: 'bg-green-100 text-green-800',
                        rejected: 'bg-red-100 text-red-800',
                    }[this.status] || 'bg-gray-100 text-gray-700';
                },
                async upload() {
                    const file = this.$refs.fileInput.files[0];
                    if (!file) {
                        this.message = 'Choose a PDF or image first.';
                        this.messageClass = 'text-red-600';
                        return;
                    }

                    const body = new FormData();
                    body.append('file', file);

                    this.submitting = true;
                    this.message = '';

                    try {
                        const response = await fetch(config.uploadUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrf,
                                'Accept': 'application/json',
                            },
                            body,
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            this.message = data.message || 'Upload failed.';
                            this.messageClass = 'text-red-600';
                            return;
                        }

                        this.status = data.status || 'uploaded';
                        this.hasFile = true;
                        this.fileName = file.name;
                        this.notes = '';
                        this.downloadUrl = config.downloadUrl;
                        this.$refs.fileInput.value = '';
                        this.message = data.message || 'Certificate uploaded.';
                        this.messageClass = 'text-green-700';
                    } catch (error) {
                        this.message = 'Upload failed.';
                        this.messageClass = 'text-red-600';
                    } finally {
                        this.submitting = false;
                    }
                },
                async remove() {
                    this.submitting = true;
                    this.message = '';

                    try {
                        const response = await fetch(config.deleteUrl, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': config.csrf,
                                'Accept': 'application/json',
                            },
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            this.message = data.message || 'Delete failed.';
                            this.messageClass = 'text-red-600';
                            return;
                        }

                        this.status = 'none';
                        this.hasFile = false;
                        this.fileName = '';
                        this.notes = '';
                        this.message = 'Certificate deleted.';
                        this.messageClass = 'text-green-700';
                    } catch (error) {
                        this.message = 'Delete failed.';
                        this.messageClass = 'text-red-600';
                    } finally {
                        this.submitting = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
