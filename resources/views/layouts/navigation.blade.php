<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
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
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if(Auth::user()->isStaff())
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Admin') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.auctions.index')" :active="request()->routeIs('admin.auctions.*')">
                            {{ __('Manage Auctions') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            {{ __('Manage Users') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">
                            {{ __('Reports') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')">
                            {{ __('Audit Logs') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.seller-applications.index')" :active="request()->routeIs('admin.seller-applications.*')">
                            {{ __('Seller Applications') }}
                            @if($pendingSellerApplications > 0)
                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">{{ $pendingSellerApplications }}</span>
                            @endif
                        </x-nav-link>
                    @endif

                    @if(Auth::user()->isVerifiedSeller())
                        <x-nav-link :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard')">{{ __('Seller Dashboard') }}</x-nav-link>
                        <x-nav-link :href="route('seller.messages.index')" :active="request()->routeIs('seller.messages.*')">
                            {{ __('Messages') }}
                            @if($unreadMessageCount > 0)
                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-700">{{ $unreadMessageCount }}</span>
                            @endif
                        </x-nav-link>
                        <x-nav-link :href="route('seller.analytics.index')" :active="request()->routeIs('seller.analytics.*')">{{ __('Analytics') }}</x-nav-link>
                        <x-nav-link :href="route('seller.revenue.index')" :active="request()->routeIs('seller.revenue.*')">{{ __('Revenue') }}</x-nav-link>
                        <x-nav-link :href="route('seller.storefront.edit')" :active="request()->routeIs('seller.storefront.*')">{{ __('Storefront Settings') }}</x-nav-link>
                    @elseif(Auth::user()->hasPendingSellerApplication())
                        <span class="inline-flex items-center text-sm text-gray-500">{{ __('Application Pending') }}</span>
                    @else
                        <x-nav-link :href="route('seller.apply.form')" :active="request()->routeIs('seller.apply.*') || request()->routeIs('seller.application.status')">{{ __('Become a Seller') }}</x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
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

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @if(Auth::user()->isStaff())
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    {{ __('Admin Monitor') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.auctions.index')" :active="request()->routeIs('admin.auctions.*')">
                    {{ __('Manage Auctions') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    {{ __('Manage Users') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">
                    {{ __('Reports') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.seller-applications.index')" :active="request()->routeIs('admin.seller-applications.*')">
                    {{ __('Seller Applications') }}
                </x-responsive-nav-link>
            @endif

            @if(Auth::user()->isVerifiedSeller())
                <x-responsive-nav-link :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard')">{{ __('Seller Dashboard') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.messages.index')" :active="request()->routeIs('seller.messages.*')">{{ __('Messages') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.analytics.index')" :active="request()->routeIs('seller.analytics.*')">{{ __('Analytics') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.revenue.index')" :active="request()->routeIs('seller.revenue.*')">{{ __('Revenue') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.storefront.edit')" :active="request()->routeIs('seller.storefront.*')">{{ __('Storefront Settings') }}</x-responsive-nav-link>
            @elseif(Auth::user()->hasPendingSellerApplication())
                <div class="px-4 py-2 text-sm text-gray-500">{{ __('Application Pending') }}</div>
            @else
                <x-responsive-nav-link :href="route('seller.apply.form')" :active="request()->routeIs('seller.apply.*') || request()->routeIs('seller.application.status')">{{ __('Become a Seller') }}</x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
