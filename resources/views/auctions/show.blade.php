<x-app-layout>
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

            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-3">
                    {{ $auction->title }}
                    @if($auction->condition)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ $auction->condition_label }}
                        </span>
                    @endif
                </h2>
                <div class="flex items-center gap-3">
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
                    @auth
                    <button id="watch-btn"
                            onclick="toggleWatch()"
                            class="inline-flex items-center min-h-11 px-3 py-2 border rounded-lg text-sm font-medium transition
                                   {{ $isWatching ? 'border-yellow-400 bg-yellow-50 text-yellow-700' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50' }}">
                        <svg class="w-4 h-4 mr-1" fill="{{ $isWatching ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <span id="watch-text">{{ $isWatching ? 'Watching' : 'Watch' }}</span>
                    </button>
                    @endauth
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

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
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Left Column: Auction Details --}}
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6 relative"
                         x-data="reportAuctionModal('{{ route('auctions.report', $auction) }}')">
                        <p class="text-gray-700 leading-relaxed mb-6">{{ $auction->description }}</p>

                        @auth
                            @if(auth()->id() !== $auction->user_id)
                                <div class="mb-6 -mt-2">
                                    <button type="button"
                                            @click="open()"
                                            class="text-sm text-gray-500 hover:text-gray-700 underline underline-offset-2">
                                        Report this listing
                                    </button>
                                </div>
                            @endif
                        @endauth

                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Starting Price</span>
                                <span class="text-lg font-bold text-gray-800">${{ number_format($auction->starting_price, 2) }}</span>
                            </div>
                            @if($prediction && ($prediction['predicted_price'] ?? 0) > 0)
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide text-indigo-600">Expected Price</span>
                                <span class="text-lg font-bold text-indigo-600">${{ number_format($prediction['predicted_price'], 2) }}</span>
                            </div>
                            @endif
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Bids</span>
                                <span id="bid-count" class="text-lg font-bold text-gray-800">{{ $auction->bids_count ?? $auction->bid_count ?? 0 }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <span class="block text-xs text-gray-500 uppercase tracking-wide">Min Increment</span>
                                <span class="text-lg font-bold text-gray-800">${{ number_format($auction->min_bid_increment, 2) }}</span>
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

                        @auth
                            @if(auth()->id() !== $auction->user_id)
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
                                        <span class="text-sm font-bold text-gray-900">${{ number_format($bid->amount, 2) }}</span>
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
                <div class="space-y-6">

                    {{-- Price & Timer Card --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="text-center mb-6">
                            <span class="block text-sm text-gray-500 uppercase tracking-wide">Current Price</span>
                            <span id="price-display" class="text-5xl font-black text-green-600 transition-colors duration-300" aria-live="polite" aria-atomic="true">
                                ${{ number_format($auction->current_price, 2) }}
                            </span>
                            @if($auction->highestBid && $auction->highestBid->user)
                                <p class="text-xs text-gray-400 mt-1">
                                    by <span id="highest-bidder" class="font-medium">{{ $auction->highestBid->user->name }}</span>
                                </p>
                            @endif
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 text-center mb-6">
                            <span class="block text-xs text-gray-500 uppercase tracking-wide">Time Remaining</span>
                            <span id="countdown" class="text-2xl font-bold text-gray-800" data-end="{{ $auction->end_time->toIso8601String() }}" role="status">
                                {{ $auction->timeRemaining() }}
                            </span>
                            @if($auction->extension_count > 0)
                                <span class="block text-xs text-orange-500 mt-1">
                                    Extended {{ $auction->extension_count }}×
                                </span>
                            @endif
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

                        {{-- Bid Form --}}
                        @if($auction->isActive())
                            <div id="error-message" class="hidden bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg mb-4 text-sm"></div>
                            <div id="success-message" class="hidden bg-green-50 border border-green-200 text-green-700 p-3 rounded-lg mb-4 text-sm"></div>

                            <form id="bid-form" class="space-y-4" aria-describedby="countdown">
                                <div>
                                    <label for="bid-amount" class="block text-sm font-medium text-gray-700 mb-1">
                                        Your Bid <span class="text-gray-400">(min $<span id="min-bid">{{ number_format($auction->minimumNextBid(), 2) }}</span>)</span>
                                    </label>
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
                                                 class="pl-8 block w-full h-14 md:h-auto rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg md:text-xl font-semibold">
                                    </div>
                                </div>

                                <button type="submit" id="bid-btn"
                                        aria-label="Place bid on {{ $auction->title }}"
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-150 ease-in-out disabled:opacity-50 disabled:cursor-not-allowed">
                                    Place Bid
                                </button>
                                <p class="text-xs text-gray-400 text-center mt-1">Press B to focus bid input</p>
                            </form>
                        @else
                            <div class="bg-gray-100 rounded-lg p-4 text-center text-gray-500">
                                <p class="text-lg font-semibold">Auction has ended</p>
                                @if($auction->winner)
                                    <p class="mt-1">Won by <span class="font-bold text-gray-800">{{ $auction->winner->name }}</span></p>
                                    <p class="text-green-600 font-bold text-xl mt-1">${{ number_format($auction->winning_bid_amount, 2) }}</p>
                                @else
                                    <p class="mt-1 text-sm">No winner determined.</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Auto-Bid Card --}}
                    @auth
                    @if($auction->isActive() && !($isPreview ?? false))
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
                                        Active up to <span class="font-bold">${{ number_format($autoBid->max_amount, 2) }}</span>
                                    </p>
                                    <p class="text-xs text-blue-700 mt-1">Up to 3 automatic bids per activation.</p>
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
                    @endauth

                </div>
            </div>
        </div>
    </div>

    {{-- Countdown Timer Script --}}
    <script>
        (function() {
            const countdownEl = document.getElementById('countdown');
            const snipeWarningEl = document.getElementById('snipe-warning');
            const snipeThreshold = {{ $auction->snipe_threshold_seconds ?? 30 }};
            let endTime = new Date(countdownEl.dataset.end).getTime();

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
                    document.getElementById('price-display').innerText = '$' + parseFloat(data.new_price).toFixed(2);
                    // Update min bid
                    const nextMin = (parseFloat(data.new_price) + {{ (float) $auction->min_bid_increment }}).toFixed(2);
                    document.getElementById('min-bid').textContent = nextMin;
                    document.getElementById('bid-amount').min = nextMin;
                    document.getElementById('bid-amount').value = nextMin;
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

    {{-- Watch Toggle --}}
    <script>
        async function toggleWatch() {
            try {
                const response = await fetch("{{ route('auctions.watch', $auction) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    }
                });
                const data = await response.json();
                const btn = document.getElementById('watch-btn');
                const txt = document.getElementById('watch-text');

                if (data.watching) {
                    btn.classList.add('border-yellow-400', 'bg-yellow-50', 'text-yellow-700');
                    btn.classList.remove('border-gray-300', 'bg-white', 'text-gray-600');
                    txt.textContent = 'Watching';
                } else {
                    btn.classList.remove('border-yellow-400', 'bg-yellow-50', 'text-yellow-700');
                    btn.classList.add('border-gray-300', 'bg-white', 'text-gray-600');
                    txt.textContent = 'Watch';
                }
            } catch (error) {
                console.error('Watch toggle failed:', error);
            }
        }
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
                            <p class="text-sm text-blue-800">Active up to <span class="font-bold">$${parseFloat(maxAmount).toFixed(2)}</span></p>
                            <p class="text-xs text-blue-700 mt-1">Up to 3 automatic bids per activation.</p>
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

                const chart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [{
                            data: points,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(199, 210, 254, 0.45)',
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
                                    color: '#6b7280',
                                },
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                ticks: {
                                    color: '#6b7280',
                                    callback: (value) => {
                                        const num = Number(value);
                                        return Number.isInteger(num) ? `$${num}` : `$${num.toFixed(2)}`;
                                    },
                                },
                                grid: {
                                    color: 'rgba(229, 231, 235, 0.8)',
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
</x-app-layout> window.addEventListener('bid:placed', (e) => {
                    chart.data.datasets[0].data.push({
                        x: new Date().toISOString(),
                        y: parseFloat(e.detail.amount),
                    });
                    chart.update('active');
                });
            });
        </script>
    @endpush
</x-app-layout>lor: '#6b7280',
                                },
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                ticks: {
                                    color: '#6b7280',
                                    callback: (value) => {
                                        const num = Number(value);
                                        return Number.isInteger(num) ? `$${num}` : `$${num.toFixed(2)}`;
                                    },
                                },
                                grid: {
                                    color: 'rgba(229, 231, 235, 0.8)',
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