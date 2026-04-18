<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 leading-tight">
            {{ __('Explore Live Auctions') }}
        </h2>
    </x-slot>

    <div class="py-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-8">
                
                {{-- Left Sidebar: Filters --}}
                <aside class="w-full lg:w-1/4 flex-shrink-0">
                    <form method="GET" action="{{ route('auctions.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-6">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Filters</h3>
                            <a href="{{ route('auctions.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Clear All</a>
                        </div>

                        {{-- Search Filter --}}
                        <div class="mb-5">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Search</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </span>
                                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search items or seller..."
                                       class="pl-9 w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                            </div>
                        </div>

                        {{-- Category (Hidden field to keep it when searching) --}}
                        @if(request('category'))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif

                        {{-- Price Range Filter --}}
                        <div class="mb-5">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Price Range ($)</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" step="0.01" min="0"
                                       class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                                <span class="text-gray-400 text-sm">-</span>
                                <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" step="0.01" min="0"
                                       class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                            </div>
                        </div>

                        {{-- Condition Filter --}}
                        @if(isset($conditions))
                            <div class="mb-5">
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Condition</label>
                                <select name="condition" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                                    <option value="">Any Condition</option>
                                    @foreach($conditions as $val => $label)
                                        <option value="{{ $val }}" @selected(request('condition') === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- Sort Filter --}}
                        <div class="mb-6">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Sort By</label>
                            <select name="sort" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors">
                                <option value="ending_soon" @selected(request('sort', 'ending_soon') === 'ending_soon')>Ending Soonest</option>
                                <option value="newest" @selected(request('sort') === 'newest')>Newly Listed</option>
                                <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                                <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-lg text-sm transition-colors shadow-sm">
                            Apply Filters
                        </button>
                    </form>
                </aside>

                {{-- Main Listing Area --}}
                <main class="w-full lg:w-3/4">
                    {{-- Category Quick-Nav --}}
                    @if(isset($rootCategories) && $rootCategories->isNotEmpty())
                        <div class="mb-6">
                            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide">
                                <a href="{{ route('categories.index') }}"
                                   class="whitespace-nowrap inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm
                                          {{ !request('category') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200' }}">
                                    All Categories
                                </a>
                                @foreach($rootCategories as $cat)
                                    <a href="{{ route('auctions.index', ['category' => $cat->slug] + request()->except(['category', 'page'])) }}"
                                       class="whitespace-nowrap inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm
                                              {{ request('category') === $cat->slug ? 'bg-indigo-600 text-white border-transparent' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200' }}">
                                        @if($cat->icon)<i class="{{ $cat->icon }} mr-1.5 opacity-70"></i>@endif
                                        {{ $cat->name }}
                                        <span class="ml-1.5 text-xs {{ request('category') === $cat->slug ? 'text-indigo-200' : 'text-gray-400' }}">({{ $cat->auctions_count }})</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Active Filters & Result Count Summary --}}
                    <div class="flex flex-wrap items-center justify-between mb-4">
                        <p class="text-gray-600 text-sm">
                            Showing <span class="font-bold text-gray-900">{{ $auctions->firstItem() ?? 0 }}-{{ $auctions->lastItem() ?? 0 }}</span> of <span class="font-bold text-gray-900">{{ $auctions->total() }}</span> results
                        </p>
                    </div>

                    {{-- Auction Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach ($auctions as $auction)
                            <div class="flex flex-col bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300 group">
                                {{-- Image Section --}}
                                <div class="relative aspect-[4/3] bg-gray-100 overflow-hidden">
                                    @if($auction->getCoverImageUrl('gallery'))
                                        <img src="{{ $auction->getCoverImageUrl('gallery') }}" alt="{{ $auction->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                                            <svg class="w-10 h-10 opacity-20" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h16v16H4V4zm2 2v12h12V6H6zm10 9.5l-3.5-4.5-2.5 3-2-2.5L6 16h12v-0.5z"/></svg>
                                        </div>
                                    @endif

                                    {{-- Floating Badges --}}
                                    <div class="absolute top-3 left-3 flex flex-col gap-2">
                                        @if($auction->is_featured)
                                            <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-yellow-400 text-yellow-900 shadow-sm flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                Featured
                                            </span>
                                        @endif
                                        @if($auction->condition)
                                            <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-white/90 backdrop-blur-sm text-gray-700 shadow-sm">
                                                {{ $auction->condition_label }}
                                            </span>
                                        @endif
                                    </div>
                                    
                                    {{-- Watch Button (Stub) --}}
                                    <button class="absolute top-3 right-3 p-2 rounded-full bg-white/90 backdrop-blur-sm text-gray-400 hover:text-red-500 hover:bg-white transition-colors shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    </button>
                                </div>

                                {{-- Content Section --}}
                                <div class="p-5 flex flex-col flex-1">
                                    {{-- Category / Subtitle --}}
                                    <div class="flex items-center text-xs text-gray-500 mb-2 gap-2">
                                        @if($auction->categories->isNotEmpty())
                                            <span class="truncate">{{ $auction->categories->first()->name }}</span>
                                        @endif
                                        @if($auction->brand)
                                            <span class="text-gray-300">•</span>
                                            <span class="truncate font-medium">{{ $auction->brand->name }}</span>
                                        @endif
                                    </div>

                                    <h3 class="font-bold text-gray-900 leading-tight mb-2 line-clamp-2 min-h-[2.5rem]">
                                        <a href="{{ route('auctions.show', $auction) }}" class="hover:text-indigo-600 transition-colors">
                                            {{ $auction->title }}
                                        </a>
                                    </h3>

                                    {{-- Reserve Info --}}
                                    @if($auction->hasReserve())
                                        <div class="mb-4">
                                            @if($auction->reserve_met)
                                                <span class="inline-flex items-center text-xs text-green-700 bg-green-50 px-2 py-0.5 rounded font-medium border border-green-100">
                                                    Reserve met
                                                </span>
                                            @else
                                                <span class="inline-flex items-center text-xs text-orange-700 bg-orange-50 px-2 py-0.5 rounded font-medium border border-orange-100">
                                                    Reserve not met
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="mb-4">
                                            <span class="inline-flex items-center text-xs text-gray-600 bg-gray-50 px-2 py-0.5 rounded font-medium border border-gray-200">
                                                No reserve
                                            </span>
                                        </div>
                                    @endif

                                    <div class="mt-auto pt-4 border-t border-gray-100 flex items-end justify-between">
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">Current Bid</p>
                                            <div class="flex items-baseline gap-1.5">
                                                <span class="text-xl font-black text-gray-900">${{ number_format($auction->current_price, 2) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">({{ $auction->bids_count ?? $auction->bid_count ?? 0 }} bids)</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-gray-500 mb-1">Ends In</p>
                                            <span class="text-sm font-bold {{ $auction->end_time->diffInHours() < 24 ? 'text-red-600' : 'text-gray-900' }}">
                                                {{ $auction->end_time->diffForHumans(['parts' => 2, 'short' => true]) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Empty State --}}
                    @if($auctions->isEmpty())
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-16 text-center mt-6">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-50 mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">No auctions found</h3>
                            <p class="text-gray-500 text-sm max-w-sm mx-auto">We couldn't find any live auctions matching your filters. Try adjusting your search parameters or clearing filters.</p>
                            <a href="{{ route('auctions.index') }}" class="mt-6 inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors">
                                Clear All Filters
                            </a>
                        </div>
                    @endif

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $auctions->links() }}
                    </div>
                </main>
            </div>
        </div>
    </div>
</x-app-layout>
