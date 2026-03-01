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
    <div class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <!-- Background decorations -->
        <div class="absolute inset-y-0 w-full h-full -z-10 bg-white">
            <div class="absolute top-0 right-1/4 w-[800px] h-[800px] bg-indigo-50 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob"></div>
            <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-purple-50 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-2000"></div>
            <div class="absolute -bottom-32 right-1/3 w-[600px] h-[600px] bg-pink-50 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-4000"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="lg:grid lg:grid-cols-12 lg:gap-16 items-center">
                <div class="lg:col-span-6 text-center lg:text-left mb-16 lg:mb-0">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 font-medium text-sm mb-6 border border-indigo-100">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                        </span>
                        Live Auctions Running Now
                    </div>
                    <h1 class="text-5xl sm:text-6xl lg:text-7xl font-serif font-bold text-gray-900 leading-[1.1] mb-6">
                        Discover & Bid on <br/>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">Exclusive Items</span>
                    </h1>
                    <p class="text-lg sm:text-xl text-gray-600 mb-10 max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                        Join the premier marketplace for unique collectibles, electronics, and rare finds. Experience real-time bidding with secure transactions and verified sellers.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="{{ route('auctions.index') }}" class="inline-flex justify-center items-center px-8 py-4 text-base font-semibold rounded-xl text-white bg-gray-900 hover:bg-gray-800 transition-all duration-200 hover:shadow-xl hover:-translate-y-1">
                            Start Bidding
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </a>
                        <a href="{{ auth()->check() && !auth()->user()->isVerifiedSeller() ? route('seller.apply.form') : (auth()->check() ? route('seller.dashboard') : route('register')) }}" class="inline-flex justify-center items-center px-8 py-4 text-base font-semibold rounded-xl text-gray-900 bg-white border-2 border-gray-200 hover:border-gray-900 hover:bg-gray-50 transition-all duration-200">
                            Become a Seller
                        </a>
                    </div>
                    
                    <div class="mt-12 flex items-center justify-center lg:justify-start gap-8">
                        <div>
                            <p class="text-3xl font-bold text-gray-900 font-serif">10K+</p>
                            <p class="text-sm font-medium text-gray-500 mt-1">Active Auctions</p>
                        </div>
                        <div class="w-px h-12 bg-gray-200"></div>
                        <div>
                            <p class="text-3xl font-bold text-gray-900 font-serif">50K+</p>
                            <p class="text-sm font-medium text-gray-500 mt-1">Verified Users</p>
                        </div>
                        <div class="w-px h-12 bg-gray-200"></div>
                        <div>
                            <p class="text-3xl font-bold text-gray-900 font-serif">Secure</p>
                            <p class="text-sm font-medium text-gray-500 mt-1">Transactions</p>
                        </div>
                    </div>
                </div>
                
                <div class="lg:col-span-6 relative">
                    <!-- Hero Imagery - Abstract representation of an auction platform -->
                    <div class="relative w-full aspect-square max-w-lg mx-auto">
                        <div class="absolute inset-0 bg-gradient-to-tr from-indigo-100 to-white rounded-3xl transform rotate-3 scale-105 border border-white/50 shadow-2xl"></div>
                        
                        <!-- Visual App UI Mockup -->
                        <div class="absolute inset-0 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden flex flex-col transform -rotate-1 transition-transform hover:rotate-0 duration-500">
                            <!-- Mockup Header -->
                            <div class="h-12 border-b border-gray-100 flex items-center px-4 justify-between bg-gray-50/50">
                                <div class="flex gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                    <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-400"></div>
                                </div>
                                <div class="h-4 w-32 bg-gray-200 rounded-full"></div>
                                <div class="w-6 h-6 rounded-full bg-gray-200"></div>
                            </div>
                            <!-- Mockup Content -->
                            <div class="p-6 flex-1 bg-gray-50 overflow-hidden flex flex-col gap-4">
                                <div class="h-48 w-full bg-gray-200 rounded-xl overflow-hidden relative">
                                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-500 to-purple-600 opacity-80 mix-blend-multiply"></div>
                                    <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Sneaker" class="w-full h-full object-cover object-center mix-blend-overlay">
                                    <div class="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-md shadow-sm">
                                        02:14:59 Left
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="h-6 w-3/4 bg-gray-200 rounded text-gray-800 font-bold flex items-center px-2">Limited Edition Sneakers</div>
                                    <div class="flex justify-between items-end">
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Current Bid</div>
                                            <div class="text-xl font-bold text-indigo-700">$450.00</div>
                                        </div>
                                        <div class="text-sm text-gray-500">12 Bids</div>
                                    </div>
                                    <div class="w-full h-10 bg-gray-900 rounded-lg mt-2 flex items-center justify-center text-white text-sm font-medium shadow-sm">
                                        Place Bid
                                    </div>
                                </div>
                                
                                <div class="mt-4 border-t border-gray-200 pt-4 flex gap-3">
                                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex-shrink-0 bg-cover bg-center" style="background-image:url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80')"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="h-3 w-1/2 bg-gray-200 rounded"></div>
                                        <div class="h-3 w-1/4 bg-gray-200 rounded"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating elements -->
                        <div class="absolute -right-6 top-1/4 bg-white p-4 rounded-xl shadow-xl border border-gray-100 animate-bounce" style="animation-duration: 3s;">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">New highest bid!</p>
                                    <p class="text-sm font-bold text-gray-900">$475.00 by Alex***</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
