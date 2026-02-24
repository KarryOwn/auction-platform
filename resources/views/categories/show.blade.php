<x-app-layout>
    <x-slot name="header">
        {{-- Breadcrumb --}}
        <div class="flex items-center text-sm text-gray-500 mb-2">
            <a href="{{ route('categories.index') }}" class="hover:text-indigo-600">Categories</a>
            @foreach($category->breadcrumb as $crumb)
                <svg class="w-4 h-4 mx-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                @if($crumb->id === $category->id)
                    <span class="text-gray-900 font-medium">{{ $crumb->name }}</span>
                @else
                    <a href="{{ route('categories.show', $crumb) }}" class="hover:text-indigo-600">{{ $crumb->name }}</a>
                @endif
            @endforeach
        </div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-2">
            @if($category->icon)
                <i class="{{ $category->icon }} text-indigo-600"></i>
            @endif
            {{ $category->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Subcategories --}}
            @if($subcategories->isNotEmpty())
                <div class="mb-8">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Subcategories</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($subcategories as $sub)
                            <a href="{{ route('categories.show', $sub) }}"
                               class="inline-flex items-center px-4 py-2 rounded-full bg-white border border-gray-200 text-sm text-gray-700 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-700 transition">
                                @if($sub->icon)
                                    <i class="{{ $sub->icon }} mr-2 text-xs"></i>
                                @endif
                                {{ $sub->name }}
                                <span class="ml-2 text-xs text-gray-400">({{ $sub->auctions_count }})</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-col lg:flex-row gap-8">
                {{-- Sidebar Filters --}}
                <aside class="w-full lg:w-64 flex-shrink-0">
                    <form method="GET" action="{{ route('categories.show', $category) }}" class="space-y-6">
                        {{-- Search --}}
                        <div>
                            <label class="text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search in {{ $category->name }}..."
                                   class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>

                        {{-- Price Range --}}
                        <div>
                            <label class="text-sm font-medium text-gray-700">Price Range</label>
                            <div class="flex gap-2 mt-1">
                                <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" step="0.01" min="0"
                                       class="w-1/2 rounded-md border-gray-300 shadow-sm text-sm">
                                <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" step="0.01" min="0"
                                       class="w-1/2 rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                        </div>

                        {{-- Condition --}}
                        <div>
                            <label class="text-sm font-medium text-gray-700">Condition</label>
                            <select name="condition" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">All Conditions</option>
                                @foreach($conditions as $value => $label)
                                    <option value="{{ $value }}" @selected(request('condition') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Brand --}}
                        @if($brands->isNotEmpty())
                            <div>
                                <label class="text-sm font-medium text-gray-700">Brand</label>
                                <select name="brand_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">All Brands</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- Dynamic Attribute Filters --}}
                        @foreach($filterableAttributes as $attr)
                            <div>
                                <label class="text-sm font-medium text-gray-700">{{ $attr->name }}@if($attr->unit) ({{ $attr->unit }})@endif</label>
                                @if($attr->type === 'select' && is_array($attr->options))
                                    <select name="attr[{{ $attr->id }}]" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="">Any</option>
                                        @foreach($attr->options as $opt)
                                            <option value="{{ $opt }}" @selected(request("attr.{$attr->id}") === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @elseif($attr->type === 'boolean')
                                    <select name="attr[{{ $attr->id }}]" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="">Any</option>
                                        <option value="1" @selected(request("attr.{$attr->id}") === '1')>Yes</option>
                                        <option value="0" @selected(request("attr.{$attr->id}") === '0')>No</option>
                                    </select>
                                @else
                                    <input type="text" name="attr[{{ $attr->id }}]" value="{{ request("attr.{$attr->id}") }}"
                                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Filter...">
                                @endif
                            </div>
                        @endforeach

                        {{-- Sort --}}
                        <div>
                            <label class="text-sm font-medium text-gray-700">Sort By</label>
                            <select name="sort" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="ending_soon" @selected(request('sort', 'ending_soon') === 'ending_soon')>Ending Soon</option>
                                <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
                                <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                                <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 bg-indigo-600 text-white rounded-md px-4 py-2 text-sm hover:bg-indigo-700 transition">
                                Apply Filters
                            </button>
                            <a href="{{ route('categories.show', $category) }}" class="px-4 py-2 text-sm text-gray-600 border rounded-md hover:bg-gray-50 transition">
                                Clear
                            </a>
                        </div>
                    </form>
                </aside>

                {{-- Auction Grid --}}
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-gray-500">{{ $auctions->total() }} {{ Str::plural('auction', $auctions->total()) }} found</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($auctions as $auction)
                            <div class="bg-white overflow-hidden shadow-sm rounded-lg hover:shadow-md transition-shadow">
                                <div class="aspect-video bg-gray-100">
                                    @if($auction->getCoverImageUrl('gallery'))
                                        <img src="{{ $auction->getCoverImageUrl('gallery') }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">No image</div>
                                    @endif
                                </div>
                                <div class="p-4">
                                    <div class="flex items-start justify-between mb-1">
                                        <h3 class="font-bold text-gray-900 line-clamp-1">{{ $auction->title }}</h3>
                                    </div>

                                    {{-- Condition badge --}}
                                    @if($auction->condition)
                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 mb-2">
                                            {{ $auction->condition_label }}
                                        </span>
                                    @endif

                                    {{-- Brand --}}
                                    @if($auction->brand)
                                        <span class="inline-block text-xs text-gray-500 mb-2">{{ $auction->brand->name }}</span>
                                    @endif

                                    {{-- Tags --}}
                                    @if($auction->tags->isNotEmpty())
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            @foreach($auction->tags->take(3) as $tag)
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                                                      style="background-color: {{ $tag->color ?? '#e5e7eb' }}20; color: {{ $tag->color ?? '#6b7280' }}">
                                                    {{ $tag->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex items-end justify-between mt-2">
                                        <div>
                                            <span class="text-green-600 font-bold text-xl">${{ number_format($auction->current_price, 2) }}</span>
                                            <span class="block text-xs text-gray-400">{{ $auction->bids_count ?? 0 }} {{ Str::plural('bid', $auction->bids_count ?? 0) }}</span>
                                        </div>
                                        <a href="{{ route('auctions.show', $auction) }}"
                                           class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">
                                            Bid Now
                                        </a>
                                    </div>

                                    <div class="mt-2 pt-2 border-t border-gray-100">
                                        <span class="text-xs text-gray-400">Ends {{ $auction->end_time->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($auctions->isEmpty())
                        <div class="bg-white shadow-sm rounded-lg p-12 text-center">
                            <p class="text-gray-400 text-lg">No auctions in this category.</p>
                        </div>
                    @endif

                    <div class="mt-6">{{ $auctions->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
