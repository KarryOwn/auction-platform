<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Live Auctions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Category Quick-Nav --}}
            @if(isset($rootCategories) && $rootCategories->isNotEmpty())
                <div class="mb-8 flex flex-wrap gap-2">
                    <a href="{{ route('categories.index') }}"
                       class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                        All Categories
                    </a>
                    @foreach($rootCategories as $cat)
                        <a href="{{ route('categories.show', $cat) }}"
                           class="inline-flex items-center px-3 py-1.5 rounded-full text-sm bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 transition {{ request('category') === $cat->slug ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : '' }}">
                            @if($cat->icon)<i class="{{ $cat->icon }} mr-1 text-xs"></i>@endif
                            {{ $cat->name }}
                            <span class="ml-1 text-xs text-gray-400">({{ $cat->auctions_count }})</span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Filter Bar --}}
            <form method="GET" action="{{ route('auctions.index') }}" class="mb-6 bg-white rounded-lg shadow-sm p-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs font-medium text-gray-500">Search</label>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search auctions..."
                               class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="w-32">
                        <label class="text-xs font-medium text-gray-500">Min Price</label>
                        <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" step="0.01" min="0"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="w-32">
                        <label class="text-xs font-medium text-gray-500">Max Price</label>
                        <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" step="0.01" min="0"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    @if(isset($conditions))
                        <div class="w-40">
                            <label class="text-xs font-medium text-gray-500">Condition</label>
                            <select name="condition" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">All</option>
                                @foreach($conditions as $val => $label)
                                    <option value="{{ $val }}" @selected(request('condition') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="w-36">
                        <label class="text-xs font-medium text-gray-500">Sort</label>
                        <select name="sort" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="ending_soon" @selected(request('sort', 'ending_soon') === 'ending_soon')>Ending Soon</option>
                            <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
                            <option value="price_asc" @selected(request('sort') === 'price_asc')>Price ↑</option>
                            <option value="price_desc" @selected(request('sort') === 'price_desc')>Price ↓</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700 transition">
                        Filter
                    </button>
                    <a href="{{ route('auctions.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
                </div>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($auctions as $auction)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200">
                        <div class="aspect-video bg-gray-100">
                            @if($auction->getCoverImageUrl('gallery'))
                                <img src="{{ $auction->getCoverImageUrl('gallery') }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">No image</div>
                            @endif
                        </div>
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="font-bold text-lg text-gray-900 line-clamp-1">{{ $auction->title }}</h3>
                                @if($auction->is_featured)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 flex-shrink-0 ml-2">
                                        ★ Featured
                                    </span>
                                @endif
                            </div>

                            {{-- Category & condition badges --}}
                            <div class="flex flex-wrap gap-1 mb-2">
                                @if($auction->condition)
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                                        {{ $auction->condition_label }}
                                    </span>
                                @endif
                                @if($auction->brand)
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                        {{ $auction->brand->name }}
                                    </span>
                                @endif
                            </div>

                            {{-- Tags --}}
                            @if($auction->tags->isNotEmpty())
                                <div class="flex flex-wrap gap-1 mb-2">
                                    @foreach($auction->tags->take(3) as $tag)
                                        <span class="inline-block px-1.5 py-0.5 rounded-full text-xs"
                                              style="background-color: {{ $tag->color ?? '#e5e7eb' }}20; color: {{ $tag->color ?? '#6b7280' }}">
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <p class="text-gray-600 text-sm mb-4 line-clamp-2">{{ $auction->description }}</p>

                            {{-- Reserve Indicator --}}
                            @if($auction->hasReserve())
                                <div class="mb-3">
                                    @if($auction->reserve_met)
                                        <span class="inline-flex items-center text-xs text-green-600 font-medium">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Reserve met
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-xs text-orange-600 font-medium">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"/></svg>
                                            Reserve not met
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div class="flex items-end justify-between mt-auto">
                                <div>
                                    <span class="text-green-600 font-bold text-2xl">${{ number_format($auction->current_price, 2) }}</span>
                                    <span class="block text-xs text-gray-400 mt-0.5">
                                        {{ $auction->bids_count ?? $auction->bid_count ?? 0 }} bid{{ ($auction->bids_count ?? $auction->bid_count ?? 0) !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                                <a href="{{ route('auctions.show', $auction) }}"
                                   class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">
                                    Bid Now
                                </a>
                            </div>

                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                <span class="text-xs text-gray-400">
                                    Ends {{ $auction->end_time->diffForHumans() }}
                                </span>
                                @if($auction->extension_count > 0)
                                    <span class="text-xs text-orange-500 font-medium">
                                        Extended {{ $auction->extension_count }}×
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($auctions->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                    <p class="text-gray-400 text-lg">No live auctions right now.</p>
                    <p class="text-gray-400 text-sm mt-1">Check back soon!</p>
                </div>
            @endif

            <div class="mt-6">
                {{ $auctions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>