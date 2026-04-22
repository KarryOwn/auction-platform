<nav x-data="{ open: false }" class="bg-white border-b border-gray-200 sticky top-0 z-40 transition-all duration-300">
    @php
        $authUser = Auth::user();
        $pendingSellerApplications = $authUser && $authUser->isStaff()
            ? \App\Models\SellerApplication::where('status', \App\Models\SellerApplication::STATUS_PENDING)->count()
            : 0;
        $unreadMessageCount = $authUser
            ? \App\Models\Conversation::query()->where(function ($q) use ($authUser) {
                $q->where(function ($sellerQuery) use ($authUser) {
                    $sellerQuery->where('seller_id', $authUser->id)
                        ->where(function ($readQuery) {
                            $readQuery->whereNull('seller_read_at')
                                ->orWhereColumn('seller_read_at', '<', 'last_message_at');
                        });
                })->orWhere(function ($buyerQuery) use ($authUser) {
                    $buyerQuery->where('buyer_id', $authUser->id)
                        ->where(function ($readQuery) {
                            $readQuery->whereNull('buyer_read_at')
                                ->orWhereColumn('buyer_read_at', '<', 'last_message_at');
                        });
                });
            })->count()
            : 0;
    @endphp
    @php($selectedDisplayCurrency = display_currency())
    @php($supportedDisplayCurrencies = config('auction.supported_currencies', ['USD']))
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20 items-center">
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center mr-8">
                    <a href="{{ route('dashboard') }}" class="group inline-flex border-none outline-none">
                        <x-application-logo class="h-10 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-6 sm:flex items-center">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('auctions.index')" :active="request()->routeIs('auctions.index')">
                        {{ __('Browse Auctions') }}
                    </x-nav-link>

                    @auth
                    @if($authUser->isStaff())
                        <!-- Admin Dropdown -->
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-indigo-600 focus:outline-none transition duration-150 ease-in-out">
                                    <span>{{ __('Admin') }}</span>
                                    @if($pendingSellerApplications > 0)
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] bg-red-100 text-red-700 font-bold border border-red-200">{{ $pendingSellerApplications }}</span>
                                    @endif
                                    <svg class="ml-1 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('admin.dashboard')">{{ __('Dashboard') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.analytics.index')">{{ __('Analytics') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.support.index')">{{ __('Support Inbox') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.auctions.index')">{{ __('Manage Auctions') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.users.index')">{{ __('Manage Users') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.reports.index')">{{ __('Reports') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.disputes.index')">{{ __('Disputes') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.bid-retractions.index')">{{ __('Bid Retractions') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.audit-logs.index')">{{ __('Audit Logs') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.payments.index')">{{ __('Payments') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.seller-applications.index')">
                                    {{ __('Seller Applications') }}
                                    @if($pendingSellerApplications > 0)
                                        <span class="ml-1 inline-flex rounded-full text-xs bg-red-100 text-red-700 px-2">{{ $pendingSellerApplications }}</span>
                                    @endif
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    @if($authUser->isVerifiedSeller())
                        <!-- Seller Dropdown -->
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-indigo-600 focus:outline-none transition duration-150 ease-in-out">
                                    <span>{{ __('Seller Portal') }}</span>
                                    @if($unreadMessageCount > 0)
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] bg-indigo-100 text-indigo-700 font-bold border border-indigo-200">{{ $unreadMessageCount }}</span>
                                    @endif
                                    <svg class="ml-1 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('seller.dashboard')">{{ __('Dashboard') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('seller.auctions.index')">{{ __('My Auctions') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('seller.auctions.create')">{{ __('Create Auction') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('seller.messages.index')">
                                    {{ __('Messages') }}
                                    @if($unreadMessageCount > 0)
                                        <span class="ml-1 rounded-full text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5">{{ $unreadMessageCount }}</span>
                                    @endif
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('seller.analytics.index')">{{ __('Analytics') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('seller.revenue.index')">{{ __('Revenue') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('seller.tax-documents.index')">{{ __('Tax Documents') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('seller.storefront.edit')">{{ __('Storefront Settings') }}</x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @elseif($authUser->hasPendingSellerApplication())
                        <span class="inline-flex items-center text-sm font-medium text-gray-400 cursor-not-allowed">
                            <svg class="w-4 h-4 mr-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ __('Application Pending') }}
                        </span>
                    @else
                        <x-nav-link :href="route('seller.apply.form')" :active="request()->routeIs('seller.apply.*') || request()->routeIs('seller.application.status')" class="text-indigo-600 hover:text-indigo-800">
                            {{ __('Become a Seller') }}
                        </x-nav-link>
                    @endif
                    @endauth
                </div>
            </div>

            <!-- Right Actions -->
            <div class="hidden sm:flex sm:items-center sm:gap-4">
                <form method="POST" action="{{ route('preferences.currency') }}" class="flex items-center">
                    @csrf
                    <label for="display-currency-desktop" class="sr-only">Display currency</label>
                    <select id="display-currency-desktop"
                            name="currency"
                            onchange="this.form.submit()"
                            class="rounded-full border-gray-200 bg-white text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($supportedDisplayCurrencies as $currency)
                            <option value="{{ $currency }}" @selected($selectedDisplayCurrency === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </form>

                @auth
                <!-- Messages -->
                <button @click="$dispatch('open-chat')" type="button" 
                   class="relative p-1 text-gray-400 hover:text-gray-600 transition">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    @if($unreadMessageCount > 0)
                        <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full">
                            {{ $unreadMessageCount > 99 ? '99+' : $unreadMessageCount }}
                        </span>
                    @endif
                </button>

                @include('components.notification-bell')

                <!-- User Dropdown Menu -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 text-sm font-medium rounded-full text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all shadow-sm">
                            <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 font-bold flex items-center justify-center text-xs">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                            <span class="hidden lg:block">{{ Auth::user()->name }}</span>
                            <svg class="fill-current h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.bids')">
                            {{ __('My Bids') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.won-auctions')">
                            {{ __('Won Auctions') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.watchlist')">
                            {{ __('Watchlist') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.wallet')">
                            {{ __('Wallet') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.referrals')">
                            {{ __('Referrals') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.invoices')">
                            {{ __('Invoices') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.notification-preferences')">
                            {{ __('Notification Settings') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.api-tokens.index')">
                            {{ __('API Tokens') }}
                        </x-dropdown-link>

                        <div class="border-t border-gray-100 my-1"></div>
                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();"
                                    class="text-red-600 hover:text-red-700 hover:bg-red-50">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
                @else
                <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-indigo-600 transition">{{ __('Log in') }}</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="ml-4 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-full text-sm font-medium text-white hover:bg-indigo-700 transition">{{ __('Register') }}</a>
                @endif
                @endauth
            </div>

            <!-- Hamburger Button (Mobile) -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (Mobile) -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="hidden sm:hidden absolute top-20 w-full bg-white shadow-xl border-b border-gray-200"
         :class="{'block': open, 'hidden': ! open}">
        <div class="pt-2 pb-3 space-y-1">
            <form method="POST" action="{{ route('preferences.currency') }}" class="px-4 pb-2">
                @csrf
                <label for="display-currency-mobile" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Display Currency</label>
                <select id="display-currency-mobile"
                        name="currency"
                        onchange="this.form.submit()"
                        class="w-full rounded-lg border-gray-200 bg-white text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach($supportedDisplayCurrencies as $currency)
                        <option value="{{ $currency }}" @selected($selectedDisplayCurrency === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </form>

            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('auctions.index')" :active="request()->routeIs('auctions.index')">
                {{ __('Browse Auctions') }}
            </x-responsive-nav-link>

            @auth
            @if($authUser->isStaff())
                <div class="pt-4 pb-2">
                    <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Admin Portal</div>
                    <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">{{ __('Admin Monitor') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.analytics.index')" :active="request()->routeIs('admin.analytics.*')">{{ __('Analytics') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.support.index')" :active="request()->routeIs('admin.support.*')">{{ __('Support Inbox') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.auctions.index')" :active="request()->routeIs('admin.auctions.*')">{{ __('Manage Auctions') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">{{ __('Manage Users') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">{{ __('Reports') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.disputes.index')" :active="request()->routeIs('admin.disputes.*')">{{ __('Disputes') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.bid-retractions.index')" :active="request()->routeIs('admin.bid-retractions.*')">{{ __('Bid Retractions') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.payments.index')" :active="request()->routeIs('admin.payments.*')">{{ __('Payments') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.seller-applications.index')" :active="request()->routeIs('admin.seller-applications.*')">{{ __('Seller Applications') }}</x-responsive-nav-link>
                </div>
            @endif

            @if($authUser->isVerifiedSeller())
                <div class="pt-4 pb-2">
                    <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Seller Portal</div>
                    <x-responsive-nav-link :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard')">{{ __('Seller Dashboard') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.auctions.index')" :active="request()->routeIs('seller.auctions.index')">{{ __('My Auctions') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.auctions.create')" :active="request()->routeIs('seller.auctions.create')">{{ __('Create Auction') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.messages.index')" :active="request()->routeIs('seller.messages.*')">{{ __('Messages') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.analytics.index')" :active="request()->routeIs('seller.analytics.*')">{{ __('Analytics') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.revenue.index')" :active="request()->routeIs('seller.revenue.*')">{{ __('Revenue') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.storefront.edit')" :active="request()->routeIs('seller.storefront.*')">{{ __('Storefront Settings') }}</x-responsive-nav-link>
                </div>
            @elseif($authUser->hasPendingSellerApplication())
                <div class="px-4 py-3 text-sm text-amber-600 bg-amber-50 font-medium">{{ __('Application Pending') }}</div>
            @else
                <x-responsive-nav-link :href="route('seller.apply.form')" :active="request()->routeIs('seller.apply.*') || request()->routeIs('seller.application.status')">{{ __('Become a Seller') }}</x-responsive-nav-link>
            @endif
            @endauth
        </div>

        @auth
        <div class="pt-4 pb-4 border-t border-gray-200">
            <div class="px-4 flex items-center mb-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-lg">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                </div>
                <div class="ml-3">
                    <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>
                <div class="ml-auto">
                    <!-- Notification Bell Mobile (Optional) -->
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.bids')">
                    {{ __('My Bids') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.won-auctions')">
                    {{ __('Won Auctions') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.watchlist')">
                    {{ __('Watchlist') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.wallet')">
                    {{ __('Wallet') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.referrals')">
                    {{ __('Referrals') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.invoices')">
                    {{ __('Invoices') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.api-tokens.index')">
                    {{ __('API Tokens') }}
                </x-responsive-nav-link>

                @if(!$authUser->isVerifiedSeller())
                <x-responsive-nav-link :href="route('messages.index')">
                    {{ __('Messages') }}
                    @if($unreadMessageCount > 0)
                        <span class="ml-2 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">
                            {{ $unreadMessageCount }}
                        </span>
                    @endif
                </x-responsive-nav-link>
                @endif
                
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();"
                            class="text-red-600">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
        @else
        <div class="pt-4 pb-4 border-t border-gray-200">
            <div class="space-y-1">
                <x-responsive-nav-link :href="route('login')">{{ __('Log in') }}</x-responsive-nav-link>
                @if (Route::has('register'))
                    <x-responsive-nav-link :href="route('register')">{{ __('Register') }}</x-responsive-nav-link>
                @endif
            </div>
        </div>
        @endauth
    </div>
    @auth
        <x-chat-drawer />
    @endauth
</nav>
