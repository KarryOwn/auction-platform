@props([
    'auction',
    'size' => 'md',
    'showSeller' => false,
    'showCategory' => true,
])

@php
    $cardClasses = 'flex flex-col bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300 group';
    
    $titleClasses = match($size) {
        'sm' => 'font-bold text-gray-900 leading-tight mb-2 line-clamp-1',
        'lg' => 'text-xl font-bold text-gray-900 leading-tight mb-2 line-clamp-3 min-h-[4.5rem]',
        default => 'font-bold text-gray-900 leading-tight mb-2 line-clamp-2 min-h-[2.5rem]',
    };

    $bodyPadding = match($size) {
        'sm' => 'p-3',
        'lg' => 'p-6',
        default => 'p-5',
    };

    $compareId = $auction->id;
@endphp

<div {{ $attributes->merge(['class' => $cardClasses]) }}>
    {{-- Image Section --}}
    <div class="relative aspect-video bg-gray-100 overflow-hidden flex-shrink-0">
        @if($auction->getCoverImageUrl('gallery'))
            <img src="{{ $auction->getCoverImageUrl('gallery') }}" alt="{{ $auction->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-400">
                <svg class="w-10 h-10 opacity-20" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h16v16H4V4zm2 2v12h12V6H6zm10 9.5l-3.5-4.5-2.5 3-2-2.5L6 16h12v-0.5z"/></svg>
            </div>
        @endif

        {{-- Floating Badges Overlay --}}
        @if($auction->is_featured && $size !== 'sm')
            <div class="absolute top-3 right-3">
                <x-ui.badge color="amber" class="shadow-sm font-bold flex items-center gap-1">
                    ★ Featured
                </x-ui.badge>
            </div>
        @endif
    </div>

    {{-- Content Section --}}
    <div class="{{ $bodyPadding }} flex flex-col flex-1">
        
        {{-- Category & Subtitle --}}
        @if($showCategory || $showSeller)
            <div class="flex items-center text-xs text-gray-500 mb-2 gap-2">
                @if($showCategory && $auction->categories && $auction->categories->isNotEmpty())
                    <span class="truncate">{{ $auction->categories->first()->name }}</span>
                @endif
                @if($showCategory && $showSeller && $auction->categories && $auction->categories->isNotEmpty() && $auction->seller)
                    <span class="text-gray-300">•</span>
                @endif
                @if($showSeller && $auction->seller)
                    <span class="truncate font-medium">{{ $auction->seller->name }}</span>
                @endif
            </div>
        @endif

        {{-- Title --}}
        <div class="flex items-start justify-between gap-3">
            <h3 class="{{ $titleClasses }}">
                <a href="{{ route('auctions.show', $auction) }}" class="hover:text-indigo-600 transition-colors" title="{{ $auction->title }}">
                    {{ $auction->title }}
                </a>
            </h3>
            <label class="flex shrink-0 items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-600">
                <input type="checkbox"
                       data-compare-toggle="{{ $compareId }}"
                       class="h-3.5 w-3.5 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                Compare
            </label>
        </div>

        {{-- Badges Row --}}
        <div class="flex flex-wrap items-center gap-1.5 mb-3">
            @if($auction->condition)
                <x-ui.badge color="blue" size="xs">{{ $auction->condition_label }}</x-ui.badge>
            @endif
            @if($auction->hasVerifiedCertificate())
                <x-ui.badge color="green" size="xs">Authenticity verified</x-ui.badge>
            @endif
            @if($auction->brand)
                <span class="text-xs text-gray-500 font-medium">{{ $auction->brand->name }}</span>
            @endif
        </div>

        {{-- Tags --}}
        @if($size !== 'sm' && $auction->tags && $auction->tags->isNotEmpty())
            <div class="flex flex-wrap gap-1 mb-4">
                @foreach($auction->tags->take(3) as $tag)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                          style="background-color: {{ $tag->color ?? '#e5e7eb' }}20; color: {{ $tag->color ?? '#4b5563' }};">
                        {{ $tag->name }}
                    </span>
                @endforeach
                @if($auction->tags->count() > 3)
                    <span class="text-[10px] text-gray-400 font-medium">+{{ $auction->tags->count() - 3 }}</span>
                @endif
            </div>
        @endif

        {{-- Reserve Indicator --}}
        @if($size !== 'sm')
            <div class="mb-4">
                @if($auction->hasReserve())
                    @if($auction->reserve_met)
                        <x-ui.badge color="green" size="xs">Reserve met</x-ui.badge>
                    @else
                        <x-ui.badge color="amber" size="xs">Reserve not met</x-ui.badge>
                    @endif
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-50 text-gray-500 border border-gray-200">No reserve</span>
                @endif
            </div>
        @endif

        {{-- Price & Bids Block --}}
        <div class="mt-auto flex items-end justify-between {{ $size === 'sm' ? 'pt-2 mt-2 border-t border-gray-50' : '' }}">
            <div>
                <x-ui.price :amount="$auction->current_price" size="sm" :label="$size !== 'sm' ? 'Current Bid' : null" />
                @if($size !== 'sm')
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">{{ $auction->bids_count ?? $auction->bid_count ?? 0 }} bids</p>
                @endif
            </div>
            @if($size === 'sm')
               <p class="text-[10px] text-gray-500 font-medium">{{ $auction->bids_count ?? $auction->bid_count ?? 0 }} bids</p>
            @endif
        </div>
    </div>

    {{-- Footer Actions --}}
    <div class="bg-gray-50 px-{{ $size === 'sm' ? '3 py-2' : ($size === 'lg' ? '6 py-4' : '4 py-3') }} border-t border-gray-100 flex items-center justify-between">
        <div>
            <x-ui.countdown :ends-at="isset($auction->end_time) ? Carbon\Carbon::parse($auction->end_time)->toIso8601String() : now()->addDay()->toIso8601String()" size="sm" :show-label="false" />
        </div>
        <div class="flex items-center gap-2">
            <button type="button"
                    data-compare-trigger="{{ $compareId }}"
                    onclick="document.querySelector('[data-compare-toggle=&quot;{{ $compareId }}&quot;]')?.click()"
                    class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                Compare
            </button>
            <x-ui.button href="{{ route('auctions.show', $auction) }}" variant="primary" size="sm" class="!px-3 !py-1 {{ $size === 'sm' ? 'text-xs' : '' }}">
                Bid Now
            </x-ui.button>
        </div>
    </div>
</div>
