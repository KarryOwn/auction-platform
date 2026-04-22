<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'BidFlow') }} - Premier Auction Platform</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|playfair-display:600,700" rel="stylesheet" />
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .font-serif { font-family: 'Playfair Display', serif; }
        .font-sans { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="antialiased text-gray-900 bg-gray-50 font-sans">
    <!-- Navbar -->
    <nav class="fixed w-full z-50 bg-white/90 backdrop-blur-md border-b border-gray-200 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ url('/') }}" class="flex items-center gap-2 group">
                        <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center transform group-hover:-rotate-12 transition-transform duration-300 shadow-lg shadow-indigo-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <span class="font-serif font-bold text-2xl tracking-tight text-gray-900">BidFlow</span>
                    </a>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center gap-8">
                    <div class="hidden md:flex space-x-8 text-sm font-medium text-gray-600">
                        <a href="{{ route('auctions.index') }}" class="hover:text-indigo-600 transition-colors">Browse Auctions</a>
                        <a href="{{ route('categories.index') }}" class="hover:text-indigo-600 transition-colors">Categories</a>
                        <a href="#how-it-works" class="hover:text-indigo-600 transition-colors">How it Works</a>
                    </div>
                </div>

                <!-- Right Side Actions -->
                <div class="hidden md:flex items-center gap-4">
                    <form method="POST" action="{{ route('preferences.currency') }}" class="mr-2">
                        @csrf
                        <label for="welcome-display-currency" class="sr-only">Display currency</label>
                        <select id="welcome-display-currency"
                                name="currency"
                                onchange="this.form.submit()"
                                class="rounded-full border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach(config('auction.supported_currencies', ['USD']) as $currency)
                                <option value="{{ $currency }}" @selected(display_currency() === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </form>

                    @auth
                        <a href="{{ url('/dashboard') }}" class="text-sm font-medium text-gray-700 hover:text-indigo-600 transition-colors">
                            Dashboard
                        </a>
                        <!-- Profile Dropdown simplified for welcome page, user can go to dashboard -->
                        <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold ml-2">
                            {{ substr(Auth::user()->name, 0, 1) }}
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-indigo-600 transition-colors">
                            Sign In
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-200 transition-all duration-200">
                                Create Account
                            </a>
                        @endif
                    @endauth
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button type="button" class="text-gray-500 hover:text-gray-700 focus:outline-none p-2" id="mobile-menu-button">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" id="menu-icon-bars"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" id="menu-icon-close" class="hidden"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="md:hidden hidden bg-white border-b border-gray-200 absolute w-full" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="{{ route('auctions.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Browse Auctions</a>
                <a href="{{ route('categories.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Categories</a>
                <a href="#how-it-works" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">How it Works</a>
            </div>
            <div class="pt-4 pb-4 border-t border-gray-200">
                <div class="flex items-center px-5 gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="block w-full text-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="block w-1/2 text-center px-4 py-2 border border-gray-300 rounded-lg text-base font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Sign In
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="block w-1/2 text-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                Register
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
<div class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden bg-gradient-to-br from-indigo-900 to-purple-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="lg:grid lg:grid-cols-12 lg:gap-16 items-center">
            <div class="lg:col-span-6 text-center lg:text-left mb-16 lg:mb-0">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-white font-medium text-sm mb-6 border border-white/20">
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                    </span>
                    ● {{ $liveCount ?? 0 }} Live Auctions
                </div>
                <h1 class="text-5xl sm:text-6xl lg:text-7xl font-serif font-bold text-white leading-[1.1] mb-6">
                    Bid on things worth having.
                </h1>
                <p class="text-lg sm:text-xl text-indigo-200 mb-10 max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                    Verified sellers. Real-time bidding. Secure escrow.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start mb-8">
                    <a href="{{ route('auctions.index') }}" class="inline-flex justify-center items-center px-8 py-4 text-base font-semibold rounded-xl text-gray-900 bg-white hover:bg-gray-100 transition-all duration-200">
                        Browse Live Auctions
                    </a>
                    <a href="{{ auth()->check() && !auth()->user()->isVerifiedSeller() ? route('seller.apply.form') : (auth()->check() ? route('seller.dashboard') : route('register')) }}" class="inline-flex justify-center items-center px-8 py-4 text-base font-semibold rounded-xl text-white bg-transparent border-2 border-white hover:bg-white/10 transition-all duration-200">
                        Become a Seller
                    </a>
                </div>
                <div class="flex items-center justify-center lg:justify-start gap-2 text-sm text-indigo-200">
                    <span>100% Secure Escrow</span>
                    <span>&middot;</span>
                    <span>Anti-snipe Protection</span>
                    <span>&middot;</span>
                    <span>Instant Payouts</span>
                </div>
            </div>
            
            <div class="lg:col-span-6 relative">
                <div class="flex flex-col gap-4">
                    @forelse($featuredAuctions ?? [] as $auction)
                        <a href="{{ route('auctions.show', $auction) }}" class="block bg-white/10 backdrop-blur-md border border-white/20 p-4 rounded-xl hover:bg-white/20 transition-all duration-200">
                            <div class="flex items-center gap-4">
                                <div class="w-[60px] h-[60px] flex-shrink-0 bg-gray-200 rounded overflow-hidden">
                                    @if($auction->getCoverImageUrl())
                                        <img src="{{ $auction->getCoverImageUrl() }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-white font-semibold truncate">{{ $auction->title }}</h3>
                                    <div class="text-indigo-200 text-sm font-bold flex items-center gap-2">
                                        <span>{{ format_price((float) $auction->current_price) }}</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <span class="flex h-1.5 w-1.5 relative mr-1.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-green-500"></span>
                                            </span>
                                            Live
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="text-indigo-200 text-center py-8">
                            No featured auctions right now.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@if(isset($featuredCategories) && $featuredCategories->isNotEmpty())
<section class="py-16 bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-700">Featured Categories</p>
                <h2 class="mt-2 text-3xl font-bold text-gray-900 font-serif">Curated categories worth exploring</h2>
            </div>
            <a href="{{ route('categories.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                Browse all <span aria-hidden="true">&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($featuredCategories as $category)
                <a href="{{ route('categories.show', $category) }}"
                   class="group relative overflow-hidden rounded-3xl border border-gray-200 bg-gray-900 shadow-sm hover:shadow-xl transition-all duration-300 min-h-[320px]">
                    @if($category->featured_banner_path)
                        <img src="{{ asset('storage/' . $category->featured_banner_path) }}"
                             alt="{{ $category->name }}"
                             class="absolute inset-0 h-full w-full object-cover opacity-70 group-hover:scale-105 transition-transform duration-500">
                    @elseif($category->image_path)
                        <img src="{{ asset('storage/' . $category->image_path) }}"
                             alt="{{ $category->name }}"
                             class="absolute inset-0 h-full w-full object-cover opacity-70 group-hover:scale-105 transition-transform duration-500">
                    @endif

                    <div class="absolute inset-0 bg-gradient-to-br from-gray-950/85 via-gray-900/55 to-amber-900/45"></div>

                    <div class="relative flex h-full flex-col justify-between p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white backdrop-blur-sm">
                                @if($category->icon)
                                    <i class="{{ $category->icon }}"></i>
                                @endif
                                Featured
                            </div>
                            <span class="inline-flex items-center rounded-full bg-emerald-400/90 px-3 py-1 text-xs font-bold text-emerald-950">
                                {{ number_format((int) ($category->auctions_count ?? 0)) }} live
                            </span>
                        </div>

                        <div>
                            <h3 class="text-3xl font-bold text-white">{{ $category->name }}</h3>
                            <p class="mt-3 max-w-md text-sm leading-6 text-gray-200">
                                {{ $category->featured_tagline ?: ($category->description ?: 'Explore curated items in this featured category.') }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between border-t border-white/15 pt-4 text-white">
                            <span class="text-sm font-medium">Shop Now</span>
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/15 backdrop-blur-sm group-hover:bg-white/25 transition">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
<!-- Featured Auctions section -->
<section class="py-16 bg-gray-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-900 font-serif">Featured Right Now</h2>
            <a href="{{ route('auctions.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                View all <span aria-hidden="true">&rarr;</span>
            </a>
        </div>

        <div class="flex overflow-x-auto lg:grid lg:grid-cols-4 gap-6 pb-8 lg:pb-0 snap-x snap-mandatory hide-scrollbars">
            @forelse($featuredAuctions ?? [] as $auction)
                <div class="snap-start shrink-0 w-72 lg:w-auto bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                    <div class="aspect-video w-full relative bg-gray-100">
                        @if($auction->getCoverImageUrl())
                            <img src="{{ $auction->getCoverImageUrl() }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        @endif
                    </div>
                    <div class="p-5 flex flex-col flex-1">
                        @if($auction->primaryCategory->first())
                            <div class="mb-2">
                                <x-ui.badge color="indigo" size="xs">{{ $auction->primaryCategory->first()->name }}</x-ui.badge>
                            </div>
                        @endif
                        <h3 class="font-semibold text-gray-900 mb-2 truncate" title="{{ $auction->title }}">{{ $auction->title }}</h3>
                        <div class="mt-auto pt-4 flex flex-col gap-3">
                            <div class="flex items-end justify-between">
                                <div>
                                    <x-ui.price :amount="$auction->current_price" size="md" />
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $auction->bids_count ?? 0 }} bids
                                </div>
                            </div>
                            <div class="flex items-center justify-between mt-2 pt-3 border-t border-gray-100">
                                <x-ui.countdown :ends-at="$auction->end_time->toIso8601String()" size="sm" :show-label="false" />
                                <x-ui.button href="{{ route('auctions.show', $auction) }}" variant="primary" size="sm">
                                    Bid Now
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center text-gray-500">
                    No featured auctions available right now.
                </div>
            @endforelse
        </div>
    </div>
</section>

<!-- Ending Soon section -->
<section class="py-16 bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-900 font-serif flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                </span>
                Ending Soon
            </h2>
            <a href="{{ route('auctions.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                View all <span aria-hidden="true">&rarr;</span>
            </a>
        </div>

        <div class="flex overflow-x-auto lg:grid lg:grid-cols-4 gap-6 pb-8 lg:pb-0 snap-x snap-mandatory hide-scrollbars">
            @forelse($endingSoonAuctions ?? [] as $auction)
                <div class="snap-start shrink-0 w-72 lg:w-auto bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                    <div class="aspect-video w-full relative bg-gray-100">
                        @if($auction->getCoverImageUrl())
                            <img src="{{ $auction->getCoverImageUrl() }}" alt="{{ $auction->title }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        @endif
                    </div>
                    <div class="p-5 flex flex-col flex-1">
                        @if($auction->primaryCategory->first())
                            <div class="mb-2">
                                <x-ui.badge color="indigo" size="xs">{{ $auction->primaryCategory->first()->name }}</x-ui.badge>
                            </div>
                        @endif
                        <h3 class="font-semibold text-gray-900 mb-2 truncate" title="{{ $auction->title }}">{{ $auction->title }}</h3>
                        <div class="mt-auto pt-4 flex flex-col gap-3">
                            <div class="flex items-end justify-between">
                                <div>
                                    <x-ui.price :amount="$auction->current_price" size="md" />
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $auction->bids_count ?? 0 }} bids
                                </div>
                            </div>
                            <div class="flex items-center justify-between mt-2 pt-3 border-t border-gray-100">
                                <x-ui.countdown :ends-at="$auction->end_time->toIso8601String()" size="sm" :show-label="false" />
                                <x-ui.button href="{{ route('auctions.show', $auction) }}" variant="primary" size="sm">
                                    Bid Now
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center text-gray-500">
                    No auctions ending soon.
                </div>
            @endforelse
        </div>
    </div>
</section>

<!-- Features / How it works -->
    <div id="how-it-works" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-indigo-600 font-semibold tracking-wide uppercase text-sm mb-3">Simple Process</h2>
                <h3 class="text-3xl md:text-4xl font-serif font-bold text-gray-900 mb-4">How BidFlow Works</h3>
                <p class="text-lg text-gray-600">Whether you're looking for unique items or selling your own, our platform makes it incredibly simple and secure.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-12 relative">
                <!-- Connecting line for desktop -->
                <div class="hidden md:block absolute top-12 left-[15%] right-[15%] h-0.5 bg-gray-100 -z-10"></div>
                
                <!-- Step 1 -->
                <div class="relative text-center">
                    <div class="w-24 h-24 mx-auto bg-indigo-50 rounded-2xl flex items-center justify-center mb-6 border border-indigo-100 shadow-sm relative z-10">
                        <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <div class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm border-4 border-white">1</div>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Find Items</h4>
                    <p class="text-gray-600 leading-relaxed">Browse thousands of verified listings across multiple categories to find exactly what you're looking for.</p>
                </div>

                <!-- Step 2 -->
                <div class="relative text-center">
                    <div class="w-24 h-24 mx-auto bg-indigo-50 rounded-2xl flex items-center justify-center mb-6 border border-indigo-100 shadow-sm relative z-10">
                        <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <div class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm border-4 border-white">2</div>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Place Bids</h4>
                    <p class="text-gray-600 leading-relaxed">Participate in real-time auctions. Set your maximum bid and let our automatic bidding system do the work.</p>
                </div>

                <!-- Step 3 -->
                <div class="relative text-center">
                    <div class="w-24 h-24 mx-auto bg-indigo-50 rounded-2xl flex items-center justify-center mb-6 border border-indigo-100 shadow-sm relative z-10">
                        <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <div class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm border-4 border-white">3</div>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Win & Secure</h4>
                    <p class="text-gray-600 leading-relaxed">Win the auction and pay securely through our platform. Seller ships the item directly to you.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-gray-900 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-indigo-900 to-gray-800 rounded-3xl p-8 sm:p-16 text-center relative overflow-hidden shadow-2xl border border-gray-700">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 rounded-full bg-white opacity-5 mix-blend-overlay"></div>
                <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full bg-indigo-500 opacity-10 mix-blend-overlay"></div>
                
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-serif font-bold text-white mb-6 relative z-10">Ready to start bidding?</h2>
                <p class="text-indigo-100 text-lg sm:text-xl max-w-2xl mx-auto mb-10 relative z-10">Join thousands of users discovering unique items every day. Creating an account takes less than a minute.</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4 relative z-10">
                    <a href="{{ route('register') }}" class="inline-flex justify-center items-center px-8 py-4 text-base font-bold rounded-xl text-indigo-900 bg-white hover:bg-gray-50 transition-all duration-200 hover:shadow-lg">
                        Create Free Account
                    </a>
                    <a href="{{ route('auctions.index') }}" class="inline-flex justify-center items-center px-8 py-4 text-base font-bold rounded-xl text-white bg-white/10 hover:bg-white/20 border border-white/20 transition-all duration-200 backdrop-blur-sm">
                        Explore Auctions
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8 mb-12">
                <div class="col-span-2 lg:col-span-2">
                    <div class="flex items-center gap-2 mb-6">
                        <div class="w-8 h-8 bg-indigo-600 text-white rounded-lg flex items-center justify-center shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <span class="font-serif font-bold text-xl text-gray-900">BidFlow</span>
                    </div>
                    <p class="text-gray-500 text-sm max-w-sm mb-6 leading-relaxed">
                        The premier destination for buying and selling unique, rare, and high-quality items through a secure auction platform.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-bold text-gray-900 mb-4 text-sm uppercase tracking-wider">Marketplace</h4>
                    <ul class="space-y-3 text-sm text-gray-600">
                        <li><a href="{{ route('auctions.index') }}" class="hover:text-indigo-600 transition-colors">All Auctions</a></li>
                        <li><a href="{{ route('categories.index') }}" class="hover:text-indigo-600 transition-colors">Categories</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Ending Soon</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Recently Sold</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold text-gray-900 mb-4 text-sm uppercase tracking-wider">Support</h4>
                    <ul class="space-y-3 text-sm text-gray-600">
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Help Center</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Trust & Safety</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Selling Fees</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Contact Us</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-gray-900 mb-4 text-sm uppercase tracking-wider">Company</h4>
                    <ul class="space-y-3 text-sm text-gray-600">
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Careers</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="pt-8 border-t border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-gray-500">
                    &copy; {{ date('Y') }} BidFlow Platform. All rights reserved.
                </p>
                <div class="flex gap-4">
                    <!-- Social icons placeholders -->
                    <a href="#" class="text-gray-400 hover:text-indigo-600 transition-colors">
                        <span class="sr-only">Twitter</span>
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"></path></svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-indigo-600 transition-colors">
                        <span class="sr-only">GitHub</span>
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"></path></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');
        const iconBars = document.getElementById('menu-icon-bars');
        const iconClose = document.getElementById('menu-icon-close');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
            iconBars.classList.toggle('hidden');
            iconClose.classList.toggle('hidden');
        });

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 10) {
                nav.classList.add('shadow-sm');
                nav.classList.replace('bg-white/90', 'bg-white/95');
            } else {
                nav.classList.remove('shadow-sm');
                nav.classList.replace('bg-white/95', 'bg-white/90');
            }
        });
    </script>
</body>
</html>
