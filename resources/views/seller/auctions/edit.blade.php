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
                <form method="POST" action="{{ route('seller.auctions.update', $auction) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Images</h3>
                        <input type="file" name="file" class="filepond" x-ref="pondInput" multiple>
                        <p class="mt-2 text-sm text-gray-500">Drag and drop images to upload, reorder, and remove.</p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Basic Info</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <x-input-label for="title" value="Title" />
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $auction->title)" />
                                <x-input-error class="mt-2" :messages="$errors->get('title')" />
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="categories" value="Categories (Select up to 3)" />
                                    <select id="categories" name="categories[]" multiple class="mt-1 block w-full border-gray-300 rounded-md shadow-sm h-32">
                                        @php
                                            $selectedCategories = old('categories', $auction->categories->pluck('id')->toArray());
                                        @endphp
                                        @foreach($categoryOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(in_array($value, $selectedCategories))>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple. First selected is primary.</p>
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
                                <textarea id="description" name="description" rows="6" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('description', $auction->description) }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('description')" />
                            </div>
                        </div>
                    </div>

                    <div>
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
                            <x-text-input id="video_url" name="video_url" type="url" class="block w-full" x-model="url" @input="update" />
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
                        <form method="POST" action="{{ route('seller.auctions.publish', $auction) }}">
                            @csrf
                            <x-secondary-button type="submit">Publish Auction</x-secondary-button>
                        </form>

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
    </script>
</x-app-layout>
