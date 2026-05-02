<x-app-layout>
    @php($displayCurrency = display_currency())
    @php($displayRate = app(\App\Services\ExchangeRateService::class)->getRate('USD', $displayCurrency))
    @php($canUseBuyerActions = $canUseBuyerActions ?? (auth()->check() && ! auth()->user()->isStaff()))
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            {{-- Category Breadcrumbs --}}
            @php($primaryCategory = $auction->primaryCategory->first())
            @if($primaryCategory)
                <nav class="flex text-sm text-gray-500" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="{{ route('categories.index') }}" class="hover:text-indigo-600 transition">Categories</a>
                        </li>
                        @foreach($primaryCategory->ancestors as $ancestor)
                            <li>
                                <div class="flex items-center">
                                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                                    </svg>
                                    <a href="{{ route('categories.show', $ancestor) }}" class="hover:text-indigo-600 transition">{{ $ancestor->name }}</a>
                                </div>
                            </li>
                        @endforeach
                        <li aria-current="page">
                            <div class="flex items-center">
                                <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                                </svg>
                                <a href="{{ route('categories.show', $primaryCategory) }}" class="text-gray-700 font-medium hover:text-indigo-600 transition">{{ $primaryCategory->name }}</a>
                            </div>
                        </li>
                    </ol>
                </nav>
            @endif

            <div class="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <h2 class="flex w-full min-w-0 max-w-full flex-wrap items-center gap-3 overflow-hidden font-semibold text-xl text-gray-800 leading-tight sm:flex-1">
                    <span class="block min-w-0 max-w-full break-all">{{ $auction->title }}</span>
                    @if($auction->condition)
                        <span class="inline-flex shrink-0 items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ $auction->condition_label }}
                        </span>
                    @endif
                </h2>
                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    {{-- Status Badge --}}
                    @if($auction->isActive())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-1.5 animate-pulse"></span> Live
                        </span>
                    @elseif($auction->status === \App\Models\Auction::STATUS_COMPLETED)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Ended</span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">{{ ucfirst($auction->status) }}</span>
                    @endif

                    {{-- Watch Button --}}
                    @if($canUseBuyerActions && !($isPreview ?? false))
                    <div
                        x-data="watchSettings({
                            watching: {{ $isWatching ? 'true' : 'false' }},
                            watchUrl: '{{ route('auctions.watch', $auction) }}',
                            outbidThreshold: @js(old('outbid_threshold', $watcher?->outbid_threshold_amount ?? auth()->user()->default_outbid_threshold)),
                            priceAlertAt: @js(old('price_alert_at', $watcher?->price_alert_at)),
                        })"
                        @keydown.escape.window="close()"
                        class="relative"
                    >
                        <button id="watch-btn"
                                type="button"
                                @click="openModal()"
                                x-bind:aria-pressed="watching.toString()"
                                x-bind:disabled="loading"
                                x-bind:class="watching ? 'border-yellow-400 bg-yellow-50 text-yellow-700' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                                class="inline-flex items-center min-h-11 px-3 py-2 border rounded-lg text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-70">
                            <svg class="w-4 h-4 mr-1" x-bind:fill="watching ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span id="watch-text" x-text="watching ? 'Watching' : 'Watch'"></span>
                        </button>

                        <div x-show="open"
                             x-cloak
                             x-transition.opacity
                             class="fixed inset-0 z-[90] bg-slate-950/50"
                             style="display: none;"
                             @click="close()"></div>

                        <div x-show="open"
                             x-cloak
                             x-transition
                             class="fixed inset-0 z-[100] flex items-center justify-center px-4"
                             style="display: none;">
                            <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
                                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Watch settings</p>
                                        <h3 class="mt-1 text-lg font-semibold text-slate-950" x-text="watching ? 'Update watch alerts' : 'Watch this auction'"></h3>
                                        <p class="mt-1 text-sm text-slate-500">Control when you hear about outbids and set a target price alert.</p>
                                    </div>
                                    <button type="button" class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click="close()">
                                        <span class="sr-only">Close watch settings</span>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <div class="space-y-5 px-6 py-6">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <label for="watch-outbid-threshold" class="block text-sm font-medium text-slate-900">Only notify me if I am outbid by at least</label>
                                        <div class="relative mt-2">
                                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">$</span>
                                            <input id="watch-outbid-threshold"
                                                   type="number"
                                                   min="0.01"
                                                   step="0.01"
                                                   x-model="outbidThreshold"
                                                   class="w-full rounded-xl border-slate-300 pl-7 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                   placeholder="25.00">
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500">Leave blank to use standard outbid notifications.</p>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <label for="watch-price-alert" class="block text-sm font-medium text-slate-900">Alert me when price reaches</label>
                                        <div class="relative mt-2">
                                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">$</span>
                                            <input id="watch-price-alert"
                                                   type="number"
                                                   min="0.01"
                                                   step="0.01"
                                                   x-model="priceAlertAt"
                                                   class="w-full rounded-xl border-slate-300 pl-7 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                   placeholder="1000.00">
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500">We only send this alert once for each watched auction.</p>
                                    </div>

                                    <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                        <button type="button"
                                                x-show="watching"
                                                x-bind:disabled="loading"
                                                @click="removeWatch()"
                                                class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50 disabled:opacity-60">
                                            Remove from watchlist
                                        </button>
                                        <div class="flex flex-1 items-center justify-end gap-3">
                                            <button type="button"
                                                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                                    @click="close()">
                                                Cancel
                                            </button>
                                            <button type="button"
                                                    x-bind:disabled="loading"
                                                    @click="save()"
                                                    class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60">
                                                <span x-show="!loading" x-text="watching ? 'Save settings' : 'Start watching'"></span>
                                                <span x-show="loading">Saving...</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($isPreview ?? false)
                <div class="rounded-2xl border-2 border-amber-300 bg-amber-50 px-6 py-4 text-amber-900 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold">Preview Mode - this auction is not yet published.</p>
                        <p class="mt-1 text-sm text-amber-800">This preview hides buyer actions and shows the draft exactly as it will appear after publishing.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('seller.auctions.edit', $auction) }}"
                           class="inline-flex items-center px-3 py-2 rounded-md border border-amber-300 bg-white text-amber-900 text-sm font-medium hover:bg-amber-100 transition">
                            Back to Edit
                        </a>
                        <form method="POST" action="{{ route('seller.auctions.publish', $auction) }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 transition">
                                Publish Now
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @php($galleryImages = $auction->getGalleryImages())
            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ images: @js($galleryImages), activeImage: @js($galleryImages[0]['full_url'] ?? null) }" aria-label="Auction images">
                @if(!empty($galleryImages))
                    <div class="aspect-[16/10] rounded-lg overflow-hidden bg-gray-100 mb-4">
                        <img :src="activeImage" class="w-full h-full object-contain" alt="{{ $auction->title }}">
                    </div>
                    <div class="grid grid-cols-5 md:grid-cols-8 gap-2">
                        <template x-for="image in images" :key="image.id">
                            <button type="button" class="border rounded overflow-hidden" @click="activeImage = image.full_url">
                                <img :src="image.thumbnail_url" class="w-full h-16 object-cover" alt="thumbnail">
                            </button>
                        </template>
                    </div>
                @else
                    <div class="aspect-[16/10] rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">No images uploaded</div>
                @endif
            </div>

            @if($auction->hasVideo() && $auction->getVideoEmbedUrl())
                <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ play: false }">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Video</h3>
                    <div class="aspect-video rounded-lg overflow-hidden bg-black relative">
                        <button x-show="!play" @click="play = true" type="button" class="absolute inset-0 flex items-center justify-center bg-black/40 text-white text-lg font-semibold">
                            ▶ Play Video
                        </button>
                        <iframe x-show="play" class="w-full h-full" :src="play ? '{{ $auction->getVideoEmbedUrl() }}' : ''" loading="lazy" allowfullscreen></iframe>
                    </div>
                </div>
            @endif

            {{-- Main Grid: Info + Bidding --}}
            <div class="grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- Left Column: Auction Details --}}
                <div class="min-w-0 space-y-6 lg:col-span-2">
                    <div class="relative min-w-0 bg-white shadow-sm sm:rounded-lg p-6"
                         x-data="reportAuctionModal('{{ route('auctions.report', $auction) }}')">
                        <p class="mb-6 min-w-0 whitespace-pre-line break-all leading-relaxed text-gray-700">{{ $auction->description }}</p>

                        @auth
                            @if(auth()->id() !== $auction->user_id)
                                <div class="mb-6 -mt-2">
                                    <button type="button"
                                            @click="open()"
                                            aria-label="Report this listing"
                                            class="text-sm text-gray-500 hover:text-gray-700 underline underline-offset-2">
                                        Report this listing
                                    </button>
                                </div>
                            @endif
                        @endauth

                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Starting Price</span>
                                <span class="text-lg font-bold text-gray-800">{{ format_price((float) $auction->starting_price) }}</span>
                            </div>
                            @if($prediction && ($prediction['predicted_price'] ?? 0) > 0)
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide text-indigo-600">Expected Price</span>
                                <span class="text-lg font-bold text-indigo-600">{{ format_price((float) $prediction['predicted_price']) }}</span>
                            </div>
                            @endif
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Bids</span>
                                <span id="bid-count" class="text-lg font-bold text-gray-800">{{ $auction->bids_count ?? $auction->bid_count ?? 0 }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Min Increment</span>
                                <span class="text-lg font-bold text-gray-800">{{ format_price((float) $auction->min_bid_increment) }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Seller</span>
                                @if(($auction->seller->seller_slug ?? null))
                                    <a class="text-lg font-bold text-indigo-700 hover:underline" href="{{ route('storefront.show', $auction->seller->seller_slug) }}">{{ $auction->seller->name ?? 'N/A' }}</a>
                                @else
                                    <span class="text-lg font-bold text-gray-800">{{ $auction->seller->name ?? 'N/A' }}</span>
                                @endif
                            </div>
                        </div>

                        @if($auction->hasVerifiedCertificate())
                            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-green-900">Authenticity Verified</p>
                                    <p class="text-sm text-green-800">This listing includes a certificate reviewed by staff.</p>
                                </div>
                                <a href="{{ route('auctions.auth-cert.download', $auction) }}"
                                   class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-md bg-green-700 text-white text-sm font-semibold hover:bg-green-800">
                                    View Certificate
                                </a>
                            </div>
                        @elseif($auction->authenticity_cert_status === 'uploaded')
                            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-sm font-semibold text-amber-900">Certificate uploaded</p>
                                <p class="text-sm text-amber-800">Verification is pending review by staff.</p>
                            </div>
                        @endif

                        @if($auction->is_lot)
                            <details class="mt-6 overflow-hidden rounded-2xl border border-violet-200 bg-violet-50/60">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-violet-900">
                                    <span>What's Included ({{ $auction->lotItems->count() }} items)</span>
                                    <svg class="h-4 w-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </summary>
                                <div class="space-y-3 border-t border-violet-200 px-4 py-4">
                                    @foreach($auction->lotItems as $item)
                                        <div class="flex gap-4 rounded-2xl bg-white p-4 ring-1 ring-violet-100">
                                            @if($item->getFirstMediaUrl('image', 'thumbnail'))
                                                <img src="{{ $item->getFirstMediaUrl('image', 'thumbnail') }}" alt="{{ $item->name }}" class="h-16 w-16 rounded-xl object-cover">
                                            @else
                                                <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-violet-100 text-xs font-semibold text-violet-700">
                                                    Item
                                                </div>
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="font-medium text-slate-900">{{ $item->name }}</p>
                                                    <span class="text-sm text-slate-500">×{{ $item->quantity }}</span>
                                                    @if($item->condition)
                                                        <x-ui.badge color="blue" size="xs">{{ $item->condition }}</x-ui.badge>
                                                    @endif
                                                </div>
                                                @if($item->description)
                                                    <p class="mt-2 text-sm text-slate-600">{{ $item->description }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        {{-- Product Specifications --}}
                        <div class="mt-8 border-t pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Specifications</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                @if($auction->brand)
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500">Brand</span>
                                        <span class="font-medium text-gray-900">{{ $auction->brand->name }}</span>
                                    </div>
                                @endif
                                @if($auction->sku)
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500">SKU</span>
                                        <span class="font-medium text-gray-900">{{ $auction->sku }}</span>
                                    </div>
                                @endif
                                @if($auction->serial_number)
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500">Serial Number</span>
                                        <span class="font-medium text-gray-900">{{ $auction->serial_number }}</span>
                                    </div>
                                @endif
                                @if($auction->hasReserve())
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500">Reserve Price</span>
                                        <span class="font-medium text-gray-900">
                                            {{ $auction->public_reserve_price ?? 'Not disclosed' }}
                                        </span>
                                    </div>
                                @endif
                                
                                {{-- Dynamic Attributes --}}
                                @foreach($auction->attributeValues as $attrValue)
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500">{{ $attrValue->attribute->name }}</span>
                                        <span class="font-medium text-gray-900">
                                            {{ $attrValue->value }}
                                            @if($attrValue->attribute->unit)
                                                <span class="text-gray-500 text-sm ml-1">{{ $attrValue->attribute->unit }}</span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Tags --}}
                        @if($auction->tags->isNotEmpty())
                            <div class="mt-6 pt-6 border-t">
                                <h3 class="text-sm font-medium text-gray-500 mb-3">Tags</h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($auction->tags as $tag)
                                        <a href="{{ route('auctions.index', ['tag' => $tag->slug]) }}" 
                                           class="inline-flex items-center px-2.5 py-1 rounded-md text-sm transition hover:opacity-80"
                                           style="background-color: {{ $tag->color ?? '#e5e7eb' }}20; color: {{ $tag->color ?? '#4b5563' }}; border: 1px solid {{ $tag->color ?? '#e5e7eb' }}40;">
                                            {{ $tag->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <details class="mt-6 rounded-xl border border-gray-200 bg-gray-50">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900">Seller Policy</summary>
                            <div class="px-4 pb-4 text-sm text-gray-700">
                                {{ $returnPolicy }}
                            </div>
                        </details>

                        @auth
                            @if(auth()->id() !== $auction->user_id && !($isPreview ?? false))
                                <div class="mt-6 border-t pt-4">
                                    <h4 class="text-sm font-semibold text-gray-800 mb-2">Message Seller</h4>
                                    <form method="POST" action="{{ route('conversations.start', $auction) }}" class="space-y-2">
                                        @csrf
                                        <textarea name="body" rows="3" class="w-full rounded-md border-gray-300" maxlength="2000" placeholder="Ask the seller a question..." required></textarea>
                                        <button class="px-3 py-2 bg-indigo-600 text-white rounded-md text-sm">Send Message</button>
                                    </form>
                                </div>
                            @endif
                        @endauth

                        @auth
                            @if(auth()->id() !== $auction->user_id)
                                <div x-cloak
                                     x-show="isOpen"
                                     x-transition.opacity
                                     class="absolute inset-0 z-30 bg-black/50 p-4 sm:p-6">
                                    <div class="h-full w-full grid place-items-center">
                                        <div @click.outside="close()"
                                             x-transition
                                             class="w-full max-w-lg bg-white rounded-xl shadow-xl border border-gray-200 p-5 sm:p-6">
                                            <div class="flex items-center justify-between mb-4">
                                                <h3 class="text-lg font-semibold text-gray-900">Report this listing</h3>
                                                <button type="button"
                                                        @click="close()"
                                                        class="text-gray-400 hover:text-gray-600"
                                                        aria-label="Close report modal">
                                                    <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                            </div>

                                            <form @submit.prevent="submitReport" class="space-y-4">
                                                <div>
                                                    <label for="report-reason" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                                                    <select id="report-reason"
                                                            x-model="form.reason"
                                                            class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                            required>
                                                        <option value="">Select a reason</option>
                                                        <option value="Item description inaccurate">Item description inaccurate</option>
                                                        <option value="Counterfeit or fake item">Counterfeit or fake item</option>
                                                        <option value="Prohibited item">Prohibited item</option>
                                                        <option value="Seller fraud">Seller fraud</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label for="report-description" class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                                                    <textarea id="report-description"
                                                              x-model="form.description"
                                                              maxlength="500"
                                                              rows="4"
                                                              class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                              placeholder="Add more details to help our team review this report."></textarea>
                                                </div>

                                                <div class="flex items-center justify-end gap-3 pt-2">
                                                    <x-ui.button type="button" variant="secondary" @click="close()">
                                                        Cancel
                                                    </x-ui.button>
                                                    <x-ui.button type="submit" variant="danger" x-bind:disabled="submitting">
                                                        <span x-show="!submitting">Submit Report</span>
                                                        <span x-show="submitting">Submitting...</span>
                                                    </x-ui.button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endauth

                        {{-- Reserve Price Indicator --}}
                        @if($auction->hasReserve())
                            <div class="mt-4 flex items-center gap-2 text-sm">
                                @if($auction->reserve_met)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-green-100 text-green-800 font-medium">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Reserve Met
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-800 font-medium">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        Reserve Not Met
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <x-ui.card>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Price History</h3>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $auction->bids_count ?? 0 }} bids</span>
                        </div>
                        @if(($auction->bids_count ?? 0) > 0)
                            <div class="h-[120px]">
                                <canvas id="price-chart" height="120"></canvas>
                            </div>
                        @else
                            <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">No bids yet</p>
                        @endif
                    </x-ui.card>

                    {{-- Recent Bids --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Bids</h3>
                        <div id="bid-history" class="divide-y divide-gray-100">
                            @forelse($recentBids as $bid)
                                <div class="flex items-center justify-between py-3 bid-entry" data-bid-id="{{ $bid->id }}">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-semibold text-sm">
                                            {{ strtoupper(substr($bid->user->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">{{ $bid->user->name ?? 'Unknown' }}</span>
                                            @if($bid->bid_type === \App\Models\Bid::TYPE_AUTO)
                                                <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Auto</span>
                                            @endif
                                            @if($bid->is_snipe_bid)
                                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Snipe</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-bold text-gray-900">{{ format_price((float) $bid->amount) }}</span>
                                        <span class="block text-xs text-gray-400">{{ $bid->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="empty-state text-gray-400 text-sm py-4 text-center">No bids yet. Be the first!</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Questions & Answers --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Questions & Answers</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">
                                {{ $questions->count() }}
                            </span>
                        </div>

                        <div class="space-y-4">
                            @forelse($questions as $question)
                                <x-ui.card>
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $question->question }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                Asked by {{ $question->user->name ?? 'Unknown user' }}
                                                <span class="mx-1">·</span>
                                                {{ $question->created_at->diffForHumans() }}
                                            </p>
                                        </div>

                                        @auth
                                            @if(auth()->id() === $question->user_id || auth()->id() === $auction->user_id)
                                                <form method="POST" action="{{ route('questions.destroy', $question) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs text-red-600 hover:text-red-700 font-medium">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        @endauth
                                    </div>

                                    @if($question->isAnswered())
                                        <div class="mt-4 border-l-2 border-indigo-200 pl-4">
                                            <p class="text-sm text-gray-800 dark:text-gray-100">{{ $question->answer }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                Answered by {{ $question->answerer->name ?? 'Seller' }}
                                                @if($question->answered_at)
                                                    <span class="mx-1">·</span>
                                                    {{ $question->answered_at->diffForHumans() }}
                                                @endif
                                            </p>
                                        </div>
                                    @elseif(auth()->check() && auth()->id() === $auction->user_id)
                                        <form method="POST" action="{{ route('questions.answer', $question) }}" class="mt-4 space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <label for="answer-{{ $question->id }}" class="sr-only">Answer</label>
                                            <textarea id="answer-{{ $question->id }}"
                                                      name="answer"
                                                      rows="3"
                                                      maxlength="1000"
                                                      class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                      placeholder="Write your answer..."
                                                      required></textarea>
                                            <button type="submit" class="px-3 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                                                Post Answer
                                            </button>
                                        </form>
                                    @endif
                                </x-ui.card>
                            @empty
                                <p class="text-sm text-gray-500 text-center py-4">No questions yet. Be the first to ask.</p>
                            @endforelse
                        </div>

                        @auth
                            @if(auth()->id() !== $auction->user_id)
                                <div class="mt-6 pt-6 border-t border-gray-100">
                                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Ask a Question</h4>
                                    <form method="POST" action="{{ route('auctions.questions.store', $auction) }}" class="space-y-3">
                                        @csrf
                                        <label for="question" class="sr-only">Question</label>
                                        <textarea id="question"
                                                  name="question"
                                                  rows="3"
                                                  maxlength="500"
                                                  class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                  placeholder="Ask something about this item..."
                                                  required></textarea>
                                        <button type="submit" class="px-3 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                                            Submit Question
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @endauth
                    </div>
                </div>

                {{-- Right Column: Bidding Panel --}}
                <div class="min-w-0 space-y-6">

                    {{-- Price & Timer Card --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="text-center mb-6">
                            <span class="block text-sm text-gray-500 uppercase tracking-wide">Current Price</span>
                            <span id="price-display" class="text-5xl font-black text-green-600 transition-colors duration-300" aria-live="polite" aria-atomic="true">
                                {{ format_price((float) $auction->current_price) }}
                            </span>
                            <p class="mt-2 text-xs text-gray-400">Display only. Bids and payments are submitted in USD.</p>
                            @if($auction->highestBid && $auction->highestBid->user)
                                <p class="text-xs text-gray-400 mt-1">
                                    by <span id="highest-bidder" class="font-medium">{{ $auction->highestBid->user->name }}</span>
                                </p>
                            @endif
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 text-center mb-6">
                            <span class="block text-xs text-gray-500 uppercase tracking-wide">Time Remaining</span>
                            <span id="countdown" class="text-2xl font-bold text-gray-800 transition-colors motion-reduce:transition-none" data-end="{{ $auction->end_time->toIso8601String() }}" role="status" aria-live="polite" aria-atomic="true">
                                {{ $auction->timeRemaining() }}
                            </span>
                            @if($auction->extension_count > 0)
                                <span class="block text-xs text-orange-500 mt-1">
                                    Extended {{ $auction->extension_count }}×
                                </span>
                            @endif
                        </div>

                        <div
                            x-data="calendarDropdown({
                                title: @js($auction->title),
                                description: @js(str($auction->description)->limit(220)->toString()),
                                url: '{{ route('auctions.show', $auction) }}',
                                startAt: '{{ $auction->end_time->copy()->subMinutes(15)->toIso8601String() }}',
                                endAt: '{{ $auction->end_time->toIso8601String() }}'
                            })"
                            @click.outside="open = false"
                            class="mb-6"
                        >
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Calendar</p>
                                        <p class="mt-1 text-sm text-slate-700">Add the ending time to your calendar and get a reminder before the auction closes.</p>
                                    </div>
                                    <div class="relative">
                                        <button type="button"
                                                @click="open = !open"
                                                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100">
                                            Add to calendar
                                            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                        <div x-show="open"
                                             x-cloak
                                             x-transition
                                             class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                                             style="display: none;">
                                            <a x-bind:href="googleUrl()" target="_blank" rel="noopener" class="flex items-center justify-between px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50">
                                                Google Calendar
                                                <span class="text-xs text-slate-400">opens</span>
                                            </a>
                                            <a x-bind:href="yahooUrl()" target="_blank" rel="noopener" class="flex items-center justify-between px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50">
                                                Yahoo Calendar
                                                <span class="text-xs text-slate-400">opens</span>
                                            </a>
                                            <button type="button" @click="downloadIcs()" class="flex w-full items-center justify-between px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                                Download ICS
                                                <span class="text-xs text-slate-400">file</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Snipe Warning --}}
                        <div id="snipe-warning" class="hidden bg-orange-50 border border-orange-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center gap-2 text-orange-700 text-sm font-medium">
                                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Anti-snipe active — bids may extend the auction by {{ $auction->snipe_extension_seconds }}s
                            </div>
                        </div>

                        @if($auction->paused_by_vacation)
                            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-sm font-semibold text-amber-900">Seller is on vacation.</p>
                                @if($auction->seller->vacation_mode_message)
                                    <p class="mt-1 text-sm text-amber-800">{{ $auction->seller->vacation_mode_message }}</p>
                                @endif
                                @if($auction->seller->vacation_mode_ends_at)
                                    <p class="mt-1 text-xs text-amber-700">Expected return: {{ $auction->seller->vacation_mode_ends_at->format('M d, Y') }}</p>
                                @endif
                            </div>
                        @endif

                        {{-- Bid Form --}}
                        @if($auction->isActive() && !($isPreview ?? false))
                            <div id="error-message" class="hidden bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg mb-4 text-sm"></div>
                            <div id="success-message" class="hidden bg-green-50 border border-green-200 text-green-700 p-3 rounded-lg mb-4 text-sm"></div>

                            @if($canUseBuyerActions)
                                <form id="bid-form" class="space-y-4 {{ $auction->paused_by_vacation ? 'pointer-events-none opacity-50' : '' }}" aria-describedby="countdown display-minimum-note">
                                    <div>
                                        <label for="bid-amount" class="block text-sm font-medium text-gray-700 mb-1">
                                            Your Bid (USD)
                                        </label>
                                        <p id="display-minimum-note" class="mb-3 text-xs text-gray-500">
                                            Minimum bid:
                                            <span class="font-semibold text-gray-700">{{ format_price((float) $auction->minimumNextBid()) }}</span>
                                            <span class="text-gray-400">(equivalent of $<span id="min-bid-usd">{{ number_format($auction->minimumNextBid(), 2) }}</span> USD)</span>
                                        </p>
                                        <div class="grid grid-cols-3 gap-2 mb-3"
                                         x-data="quickBid({ minBid: {{ (float) $auction->minimumNextBid() }}, currentPrice: {{ (float) $auction->current_price }}, increment: {{ (float) $auction->min_bid_increment }} })"
                                         x-init="init()">
                                        <button type="button"
                                            @click="setMin()"
                                            x-text="minLabel"
                                            class="bg-indigo-50 border border-indigo-200 text-sm font-medium rounded-lg hover:bg-indigo-100 py-2 px-2 text-gray-700 transition"></button>
                                        <button type="button"
                                            @click="setPlus5()"
                                            x-text="plus5Label"
                                            class="bg-gray-50 border border-gray-200 text-sm font-medium rounded-lg hover:bg-gray-100 py-2 px-2 text-gray-700 transition"></button>
                                        <button type="button"
                                            @click="setPlus10()"
                                            x-text="plus10Label"
                                            class="bg-gray-50 border border-gray-200 text-sm font-medium rounded-lg hover:bg-gray-100 py-2 px-2 text-gray-700 transition"></button>
                                        </div>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 text-lg">$</span>
                                            <input type="number" id="bid-amount" step="0.01"
                                                     data-increment="{{ (float) $auction->min_bid_increment }}"
                                                   min="{{ $auction->minimumNextBid() }}"
                                                   value="{{ $auction->minimumNextBid() }}"
                                                     @disabled($auction->paused_by_vacation)
                                                     aria-describedby="display-minimum-note"
                                                     class="pl-8 block w-full h-14 md:h-auto rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg md:text-xl font-semibold">
                                        </div>
                                    </div>

                                    <button type="submit" id="bid-btn"
                                            @disabled($auction->paused_by_vacation)
                                            aria-label="Place bid on {{ $auction->title }}"
                                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-150 ease-in-out disabled:opacity-50 disabled:cursor-not-allowed">
                                        {{ $auction->paused_by_vacation ? 'Bidding Paused While Seller Is Away' : 'Place Bid' }}
                                    </button>
                                    <p class="text-xs text-gray-400 text-center mt-1">Press B to focus bid input</p>
                                </form>

                                @if(auth()->id() !== $auction->user_id)
                                    <x-auction.bin-button
                                        :auction="$auction"
                                        :bin-price="$auction->buy_it_now_price"
                                        :available="$auction->isBuyItNowAvailable()"
                                    />
                                @endif
                            @elseif(auth()->guest())
                                <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-5 text-center">
                                    <p class="text-sm font-semibold text-indigo-950">Sign in to bid on this auction.</p>
                                    <p class="mt-1 text-sm text-indigo-800">Guests can browse auction details, but bidding, watching, questions, and seller messages require an account.</p>
                                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-center">
                                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                                            Log in to bid
                                        </a>
                                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                            Create account
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center text-sm text-gray-600">
                                    This account can view auctions, but buyer actions are not available.
                                </div>
                            @endif
                        @elseif($isPreview ?? false)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                Buyer actions are hidden in preview mode. Publish the auction to enable bidding, watching, and reporting.
                            </div>
                        @else
                            <div class="bg-gray-100 rounded-lg p-4 text-center text-gray-500">
                                <p class="text-lg font-semibold">Auction has ended</p>
                                @if($auction->winner)
                                    <p class="mt-1">Won by <span class="font-bold text-gray-800">{{ $auction->winner->name }}</span></p>
                                    <p class="text-green-600 font-bold text-xl mt-1">{{ format_price((float) $auction->winning_bid_amount) }}</p>
                                @else
                                    <p class="mt-1 text-sm">No winner determined.</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Auto-Bid Card --}}
                    @if($canUseBuyerActions && $auction->isActive() && !($isPreview ?? false))
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/>
                            </svg>
                            Auto-Bid
                        </h3>

                        <div id="auto-bid-status">
                            @if($autoBid)
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                                    <p class="text-sm text-blue-800">
                                        Active up to <span class="font-bold">{{ format_price((float) $autoBid->max_amount) }}</span>
                                    </p>
                                    <p class="text-xs text-blue-700 mt-1">Stored and submitted in USD ({{ '$' . number_format((float) $autoBid->max_amount, 2) }}). Up to 3 automatic bids per activation.</p>
                                    <button onclick="cancelAutoBid()" class="mt-2 text-xs text-red-600 hover:text-red-800 font-medium underline">
                                        Cancel Auto-Bid
                                    </button>
                                </div>
                            @endif
                        </div>

                        <form id="auto-bid-form" class="{{ $autoBid ? 'hidden' : '' }}">
                            <label for="auto-bid-max" class="block text-xs text-gray-500 mb-1">Max bid amount</label>
                            <input type="number" id="auto-bid-max" step="0.01"
                                   min="{{ $auction->minimumNextBid() }}"
                                   placeholder="{{ number_format($auction->minimumNextBid() * 2, 2) }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm mb-2">
                            <button type="submit"
                                    class="w-full bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium py-2 px-3 rounded-lg transition">
                                Enable Auto-Bid
                            </button>
                        </form>
                        <p class="text-xs text-gray-400 mt-2">The system will automatically bid on your behalf up to your limit (max 3 auto-bids per activation).</p>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    {{-- Countdown Timer Script --}}
    <script>
        (function() {
            const displayCurrency = @json($displayCurrency);
            const displayRate = {{ json_encode($displayRate) }};
            const zeroDecimalCurrencies = ['JPY', 'VND'];
            const countdownEl = document.getElementById('countdown');
            const snipeWarningEl = document.getElementById('snipe-warning');
            const snipeThreshold = {{ $auction->snipe_threshold_seconds ?? 30 }};
            let endTime = new Date(countdownEl.dataset.end).getTime();

            window.formatDisplayPrice = function(amountUsd) {
                const converted = Number(amountUsd) * Number(displayRate || 1);
                const decimals = zeroDecimalCurrencies.includes(displayCurrency) ? 0 : 2;
                const localized = Number(converted).toLocaleString(undefined, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                });

                switch (displayCurrency) {
                    case 'EUR':
                        return '€' + localized;
                    case 'GBP':
                        return '£' + localized;
                    case 'JPY':
                        return '¥' + localized;
                    case 'VND':
                        return '₫' + localized;
                    default:
                        return '$' + Number(amountUsd).toFixed(2);
                }
            };

            window.updateBidMinimum = function(nextMinUsd) {
                const numericUsd = Number(nextMinUsd);

                if (!Number.isFinite(numericUsd)) {
                    return;
                }

                const input = document.getElementById('bid-amount');
                const displayNoteEl = document.getElementById('display-minimum-note');

                if (displayNoteEl) {
                    displayNoteEl.innerHTML = `Minimum bid: <span class="font-semibold text-gray-700">${window.formatDisplayPrice(numericUsd)}</span> <span class="text-gray-400">(equivalent of $<span id="min-bid-usd">${numericUsd.toFixed(2)}</span> USD)</span>`;
                }

                if (input) {
                    input.min = numericUsd.toFixed(2);
                    input.value = numericUsd.toFixed(2);
                }
            };

            function updateCountdown() {
                const now = Date.now();
                const diff = endTime - now;

                if (diff <= 0) {
                    countdownEl.textContent = 'Ended';
                    countdownEl.classList.add('text-red-600');
                    if (snipeWarningEl) snipeWarningEl.classList.add('hidden');
                    return;
                }

                // Show snipe warning when within threshold
                const secondsLeft = Math.floor(diff / 1000);
                if (secondsLeft <= snipeThreshold) {
                    snipeWarningEl?.classList.remove('hidden');
                    countdownEl.classList.add('text-orange-600');
                    countdownEl.classList.remove('text-gray-800');
                } else {
                    snipeWarningEl?.classList.add('hidden');
                    countdownEl.classList.remove('text-orange-600');
                    countdownEl.classList.add('text-gray-800');
                }

                const days = Math.floor(diff / 86400000);
                const hours = Math.floor((diff % 86400000) / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                const seconds = Math.floor((diff % 60000) / 1000);

                let parts = [];
                if (days > 0) parts.push(days + 'd');
                if (hours > 0) parts.push(hours + 'h');
                parts.push(minutes + 'm');
                parts.push(seconds + 's');

                countdownEl.textContent = parts.join(' ');
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);

            // Expose a way to extend end time from WebSocket events
            window.updateAuctionEndTime = function(newEndTime) {
                endTime = new Date(newEndTime).getTime();
            };
        })();
    </script>

    {{-- Bid Form Script --}}
    <script>
        document.getElementById('bid-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('bid-btn');
            const amount = document.getElementById('bid-amount').value;
            const errorDiv = document.getElementById('error-message');
            const successDiv = document.getElementById('success-message');

            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            btn.disabled = true;
            btn.textContent = 'Placing...';

            try {
                const response = await fetch("{{ route('auctions.bid', $auction) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ amount })
                });

                const data = await response.json();

                if (response.ok) {
                    successDiv.textContent = data.message;
                    successDiv.classList.remove('hidden');
                    document.getElementById('price-display').innerText = data.display_price ?? window.formatDisplayPrice(data.new_price);
                    window.updateBidMinimum(data.minimum_next_bid ?? (parseFloat(data.new_price) + {{ (float) $auction->min_bid_increment }}));
                } else {
                    errorDiv.textContent = data.message || 'Bid rejected.';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Place Bid';
            }
        });
    </script>

    <script>
        function watchSettings(config) {
            return {
                open: false,
                loading: false,
                watching: Boolean(config.watching),
                watchUrl: config.watchUrl,
                outbidThreshold: config.outbidThreshold ?? '',
                priceAlertAt: config.priceAlertAt ?? '',
                openModal() {
                    this.open = true;
                },
                close() {
                    if (this.loading) {
                        return;
                    }

                    this.open = false;
                },
                normalizedValue(value) {
                    if (value === '' || value === null || value === undefined) {
                        return null;
                    }

                    const parsed = Number.parseFloat(value);

                    return Number.isFinite(parsed) && parsed > 0 ? parsed.toFixed(2) : null;
                },
                async request(payload = {}) {
                    const response = await fetch(this.watchUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to update watch settings.');
                    }

                    return data;
                },
                async save() {
                    this.loading = true;

                    try {
                        const data = await this.request({
                            outbid_threshold: this.normalizedValue(this.outbidThreshold),
                            price_alert_at: this.normalizedValue(this.priceAlertAt),
                        });

                        this.watching = Boolean(data.watching);
                        this.open = false;
                        window.toast?.success(data.message || 'Watch settings saved.');
                    } catch (error) {
                        window.toast?.error(error.message || 'Unable to update your watchlist right now.');
                    } finally {
                        this.loading = false;
                    }
                },
                async removeWatch() {
                    this.loading = true;

                    try {
                        const data = await this.request();
                        this.watching = Boolean(data.watching);
                        this.open = false;
                        window.toast?.success(data.message || 'Removed from watchlist.');
                    } catch (error) {
                        window.toast?.error(error.message || 'Unable to update your watchlist right now.');
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }

        function calendarDropdown(config) {
            return {
                open: false,
                title: config.title,
                description: config.description,
                url: config.url,
                startAt: config.startAt,
                endAt: config.endAt,
                formatDate(value) {
                    return new Date(value).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
                },
                googleUrl() {
                    const params = new URLSearchParams({
                        action: 'TEMPLATE',
                        text: `${this.title} auction ending`,
                        details: `${this.description}\n\nView auction: ${this.url}`,
                        dates: `${this.formatDate(this.startAt)}/${this.formatDate(this.endAt)}`,
                    });

                    return `https://calendar.google.com/calendar/render?${params.toString()}`;
                },
                yahooUrl() {
                    const params = new URLSearchParams({
                        v: '60',
                        view: 'd',
                        type: '20',
                        title: `${this.title} auction ending`,
                        desc: `${this.description}\n\nView auction: ${this.url}`,
                        st: this.formatDate(this.startAt),
                        et: this.formatDate(this.endAt),
                    });

                    return `https://calendar.yahoo.com/?${params.toString()}`;
                },
                downloadIcs() {
                    const ics = [
                        'BEGIN:VCALENDAR',
                        'VERSION:2.0',
                        'PRODID:-//Auction Platform//Auction Reminder//EN',
                        'BEGIN:VEVENT',
                        'UID:auction-{{ $auction->id }}@auction-platform',
                        `DTSTAMP:${this.formatDate(new Date().toISOString())}`,
                        `DTSTART:${this.formatDate(this.startAt)}`,
                        `DTEND:${this.formatDate(this.endAt)}`,
                        `SUMMARY:${this.title.replace(/\n/g, ' ')}`,
                        `DESCRIPTION:${`${this.description}\n\nView auction: ${this.url}`.replace(/\n/g, '\\n')}`,
                        `URL:${this.url}`,
                        'END:VEVENT',
                        'END:VCALENDAR',
                    ].join('\r\n');

                    const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
                    const objectUrl = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = objectUrl;
                    link.download = 'auction-reminder.ics';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    window.URL.revokeObjectURL(objectUrl);
                    this.open = false;
                },
            };
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.watchSettings = watchSettings;
            window.calendarDropdown = calendarDropdown;
        });
    </script>

    <script>
        if (window.Echo) {
            window.Echo.channel('auctions.{{ $auction->id }}')
                .listen('.bid.placed', () => {
                    const watchButton = document.getElementById('watch-btn');
                    if (watchButton) {
                        watchButton.dispatchEvent(new CustomEvent('watch-refresh', { bubbles: true }));
                    }
                });
        }
    </script>

    <script>
        document.addEventListener('watch-refresh', async () => {
            try {
                const response = await fetch("{{ route('auctions.live-state', $auction) }}", {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                if (!data.buy_it_now_available) {
                    window.dispatchEvent(new CustomEvent('auction:bin-unavailable', {
                        detail: { auctionId: {{ $auction->id }} }
                    }));
                }
            } catch (_) {
                // Fall back to the regular live-state polling already running on the page.
            }
        });
    </script>

    {{-- Auto-Bid Scripts --}}
    <script>
        document.getElementById('auto-bid-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const maxAmount = document.getElementById('auto-bid-max').value;

            try {
                const response = await fetch("{{ route('auctions.auto-bid', $auction) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ max_amount: maxAmount })
                });

                const data = await response.json();
                if (response.ok) {
                    document.getElementById('auto-bid-status').innerHTML = `
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                            <p class="text-sm text-blue-800">Active up to <span class="font-bold">${window.formatDisplayPrice(parseFloat(maxAmount))}</span></p>
                            <p class="text-xs text-blue-700 mt-1">Stored and submitted in USD ($${parseFloat(maxAmount).toFixed(2)}). Up to 3 automatic bids per activation.</p>
                            <button onclick="cancelAutoBid()" class="mt-2 text-xs text-red-600 hover:text-red-800 font-medium underline">Cancel Auto-Bid</button>
                        </div>`;
                    document.getElementById('auto-bid-form').classList.add('hidden');
                }
            } catch (error) {
                console.error('Auto-bid setup failed:', error);
            }
        });

        async function cancelAutoBid() {
            try {
                const response = await fetch("{{ route('auctions.cancel-auto-bid', $auction) }}", {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    }
                });
                if (response.ok) {
                    document.getElementById('auto-bid-status').innerHTML = '';
                    document.getElementById('auto-bid-form').classList.remove('hidden');
                }
            } catch (error) {
                console.error('Auto-bid cancel failed:', error);
            }
        }
    </script>

    {{-- Real-Time WebSocket Events --}}
    <script>
        (function () {
            const auctionId = {{ $auction->id }};

            function trySubscribe() {
                if (window.__auctionRealtimeSubscribed === auctionId) {
                    return true;
                }

                if (window.BidEventBus && typeof window.BidEventBus.subscribe === 'function') {
                    window.BidEventBus.subscribe(auctionId);
                    window.__auctionRealtimeSubscribed = auctionId;
                    return true;
                }

                return false;
            }

            if (!trySubscribe()) {
                document.addEventListener('DOMContentLoaded', trySubscribe, { once: true });
                window.addEventListener('load', trySubscribe, { once: true });

                // Last-resort retry for slow bundle hydration.
                setTimeout(trySubscribe, 300);
            }
        })();
    </script>

    <script>
        (function () {
            const auctionId = {{ $auction->id }};
            const endpoint = "{{ route('auctions.live-state', $auction) }}";
            let lastSeenBidId = Number(document.querySelector('#bid-history .bid-entry')?.dataset?.bidId || 0);

            async function syncLiveState() {
                try {
                    const response = await fetch(endpoint, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    const currentPrice = Number(data.current_price ?? data.new_price);

                    if (Number.isFinite(currentPrice)) {
                        const priceEl = document.getElementById('price-display');
                        if (priceEl) {
                            priceEl.textContent = data.display_price ?? window.formatDisplayPrice(currentPrice);
                        }

                        window.updateBidMinimum(data.next_minimum ?? (currentPrice + {{ (float) $auction->min_bid_increment }}));

                        window.dispatchEvent(new CustomEvent('price:updated', {
                            detail: {
                                auctionId,
                                newPrice: currentPrice,
                                new_price: currentPrice,
                                next_minimum: data.next_minimum,
                            },
                        }));
                    }

                    if (data.bid_count !== undefined) {
                        const countEl = document.getElementById('bid-count');
                        if (countEl) {
                            countEl.textContent = String(data.bid_count);
                        }
                    }

                    if (data.highest_bidder_name) {
                        const bidderEl = document.getElementById('highest-bidder');
                        if (bidderEl) {
                            bidderEl.textContent = data.highest_bidder_name;
                        }
                    }

                    window.dispatchEvent(new CustomEvent('auction:live-state', {
                        detail: {
                            auctionId,
                            ...data,
                        },
                    }));

                    if (!Array.isArray(data.recent_bids) || data.recent_bids.length === 0) {
                        return;
                    }

                    const newestId = Number(data.recent_bids[0]?.id || 0);
                    if (!Number.isFinite(newestId) || newestId <= lastSeenBidId) {
                        return;
                    }

                    data.recent_bids
                        .slice()
                        .reverse()
                        .forEach((bid) => {
                            const bidId = Number(bid.id || 0);
                            if (!Number.isFinite(bidId) || bidId <= lastSeenBidId) {
                                return;
                            }

                            window.dispatchEvent(new CustomEvent('bid:placed', {
                                detail: {
                                    auctionId,
                                    bid_id: bidId,
                                    amount: bid.amount,
                                    bid_type: bid.bid_type,
                                    is_snipe_bid: bid.is_snipe_bid,
                                    user_name: bid.bidder_name,
                                    bidder_name: bid.bidder_name,
                                    bid_count: data.bid_count,
                                    bids_count: data.bid_count,
                                    next_minimum: data.next_minimum,
                                    created_at_human: bid.created_at_human,
                                },
                            }));
                        });

                    lastSeenBidId = newestId;
                } catch (_) {
                    // No-op fallback failure; websocket may still be healthy.
                }
            }

            setTimeout(syncLiveState, 1500);
            setInterval(syncLiveState, 4000);

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    syncLiveState();
                }
            });
        })();
    </script>

    <script>
        window.reportAuctionModal = function(reportUrl) {
            return {
                isOpen: false,
                submitting: false,
                form: {
                    reason: '',
                    description: '',
                },
                open() {
                    this.isOpen = true;
                },
                close() {
                    this.isOpen = false;
                },
                async submitReport() {
                    if (this.submitting) {
                        return;
                    }

                    this.submitting = true;

                    try {
                        const response = await fetch(reportUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                reason: this.form.reason,
                                description: this.form.description,
                            }),
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.form.reason = '';
                            this.form.description = '';
                            this.close();
                            window.toast?.success(data.message || 'Report submitted.');
                            return;
                        }

                        window.toast?.error(data.message || 'Unable to submit report.');
                    } catch (error) {
                        window.toast?.error('Request failed.');
                    } finally {
                        this.submitting = false;
                    }
                },
            };
        };
    </script>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const canvas = document.getElementById('price-chart');
                if (!canvas || typeof Chart === 'undefined') {
                    return;
                }

                const points = @json(json_decode($bidChartData, true));
                const showPoints = window.matchMedia('(min-width: 640px)').matches;
                const css = getComputedStyle(document.documentElement);
                const token = (name, fallback) => css.getPropertyValue(name).trim() || fallback;

                const chart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [{
                            data: points,
                            borderColor: token('--color-primary', '#6366f1'),
                            backgroundColor: 'color-mix(in srgb, ' + token('--color-primary', '#6366f1') + ' 24%, transparent)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: showPoints ? 2 : 0,
                            pointHoverRadius: showPoints ? 3 : 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false },
                            title: { display: false },
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    displayFormats: { minute: 'HH:mm', hour: 'HH:mm' },
                                    tooltipFormat: 'HH:mm',
                                },
                                ticks: {
                                    color: token('--color-text-secondary', '#6b7280'),
                                },
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                ticks: {
                                    color: token('--color-text-secondary', '#6b7280'),
                                    callback: (value) => {
                                        const num = Number(value);
                                        return Number.isInteger(num) ? `$${num}` : `$${num.toFixed(2)}`;
                                    },
                                },
                                grid: {
                                    color: 'color-mix(in srgb, ' + token('--color-text-muted', '#9ca3af') + ' 28%, transparent)',
                                },
                            },
                        },
                    },
                });

                window.addEventListener('bid:placed', (e) => {
                    chart.data.datasets[0].data.push({
                        x: new Date().toISOString(),
                        y: parseFloat(e.detail.amount),
                    });
                    chart.update('active');
                });
            });
        </script>
    @endpush
</x-app-layout>
