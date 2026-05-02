<nav x-data="{ open: false }" class="theme-topbar sticky top-0 z-40 transition-all duration-300">
    @php
        $authUser = Auth::user();
        $isStaffNavigation = $authUser?->isStaff() ?? false;
        $isSellerNavigation = ! $isStaffNavigation && ($authUser?->isVerifiedSeller() ?? false);
        $dashboardRoute = $isStaffNavigation ? route('admin.dashboard') : route('dashboard');
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

        $adminBadgeCounts = [];

        if ($isStaffNavigation) {
            $adminBadgeCounts = \Illuminate\Support\Facades\Cache::remember('admin:navigation:badge-counts', 30, fn () => [
                'support' => \App\Models\SupportConversation::query()
                    ->whereIn('status', [
                        \App\Models\SupportConversation::STATUS_OPEN,
                        \App\Models\SupportConversation::STATUS_ESCALATED,
                    ])->count(),
                'seller_approvals' => \App\Models\SellerApplication::query()
                    ->where('status', \App\Models\SellerApplication::STATUS_PENDING)
                    ->count(),
                'certificate_approvals' => \App\Models\Auction::query()
                    ->where('has_authenticity_cert', true)
                    ->where('authenticity_cert_status', 'uploaded')
                    ->count(),
                'reports' => \App\Models\ReportedAuction::query()
                    ->where('status', 'pending')
                    ->count(),
                'disputes' => \App\Models\Dispute::query()
                    ->whereIn('status', [
                        \App\Models\Dispute::STATUS_OPEN,
                        \App\Models\Dispute::STATUS_UNDER_REVIEW,
                    ])->count(),
                'bid_retractions' => \App\Models\BidRetractionRequest::query()
                    ->where('status', 'pending')
                    ->count(),
                'webhooks' => \App\Models\WebhookDelivery::query()
                    ->where('status', 'failed')
                    ->count(),
            ]);
        }

        $adminBadge = fn (string $key): ?string => ($adminBadgeCounts[$key] ?? 0) > 0
            ? (($adminBadgeCounts[$key] ?? 0) > 99 ? '99+' : (string) $adminBadgeCounts[$key])
            : null;

        $adminGroups = $isStaffNavigation ? [
            [
                'label' => 'Monitor',
                'icon' => 'chart',
                'active' => request()->routeIs('admin.dashboard') || request()->routeIs('admin.analytics.*'),
                'items' => [
                    ['label' => 'Dashboard', 'description' => 'Live KPIs and platform health', 'href' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard')],
                    ['label' => 'Analytics Overview', 'description' => 'Revenue, bids, and traffic reports', 'href' => route('admin.analytics.index'), 'active' => request()->routeIs('admin.analytics.index')],
                    ['label' => 'Category Analytics', 'description' => 'Category performance reporting', 'href' => route('admin.analytics.categories'), 'active' => request()->routeIs('admin.analytics.categories')],
                    ['label' => 'Bid Timing Heatmap', 'description' => 'Bidding behavior by time window', 'href' => route('admin.analytics.bid-timing'), 'active' => request()->routeIs('admin.analytics.bid-timing')],
                    ['label' => 'Seller Leaderboard', 'description' => 'Top seller performance', 'href' => route('admin.analytics.leaderboard'), 'active' => request()->routeIs('admin.analytics.leaderboard')],
                    ['label' => 'Buyer Analytics', 'description' => 'Buyer activity and risk patterns', 'href' => route('admin.analytics.buyers'), 'active' => request()->routeIs('admin.analytics.buyers*')],
                ],
            ],
            [
                'label' => 'Operations',
                'icon' => 'gavel',
                'active' => request()->routeIs('admin.auctions.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.reports.*') || request()->routeIs('admin.disputes.*') || request()->routeIs('admin.bid-retractions.*') || request()->routeIs('admin.payments.*'),
                'items' => [
                    ['label' => 'Manage Auctions', 'description' => 'Review, feature, extend, or cancel auctions', 'href' => route('admin.auctions.index'), 'active' => request()->routeIs('admin.auctions.*') && request('auth_cert') !== 'uploaded'],
                    ['label' => 'Manage Users', 'description' => 'User profiles, bans, and role changes', 'href' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*')],
                    ['label' => 'Reports', 'description' => 'Reported auction review queue', 'href' => route('admin.reports.index'), 'active' => request()->routeIs('admin.reports.*'), 'badge' => $adminBadge('reports')],
                    ['label' => 'Disputes', 'description' => 'Buyer and seller dispute cases', 'href' => route('admin.disputes.index'), 'active' => request()->routeIs('admin.disputes.*'), 'badge' => $adminBadge('disputes')],
                    ['label' => 'Bid Retractions', 'description' => 'Pending retraction decisions', 'href' => route('admin.bid-retractions.index'), 'active' => request()->routeIs('admin.bid-retractions.*'), 'badge' => $adminBadge('bid_retractions')],
                    ['label' => 'Payments', 'description' => 'Escrow holds and invoices', 'href' => route('admin.payments.index'), 'active' => request()->routeIs('admin.payments.index')],
                    ['label' => 'Transactions', 'description' => 'Payment transaction ledger', 'href' => route('admin.payments.transactions'), 'active' => request()->routeIs('admin.payments.transactions')],
                ],
            ],
            [
                'label' => 'Approvals',
                'icon' => 'shield',
                'active' => request()->routeIs('admin.seller-applications.*') || (request()->routeIs('admin.auctions.*') && request('auth_cert') === 'uploaded'),
                'items' => [
                    ['label' => 'Seller Approvals', 'description' => 'Pending seller applications', 'href' => route('admin.seller-applications.index'), 'active' => request()->routeIs('admin.seller-applications.*'), 'badge' => $adminBadge('seller_approvals')],
                    ['label' => 'Certificate Approvals', 'description' => 'Uploaded authenticity certificates', 'href' => route('admin.auctions.index', ['auth_cert' => 'uploaded']), 'active' => request()->routeIs('admin.auctions.*') && request('auth_cert') === 'uploaded', 'badge' => $adminBadge('certificate_approvals')],
                ],
            ],
            [
                'label' => 'Support',
                'icon' => 'message',
                'active' => request()->routeIs('admin.support.*'),
                'items' => [
                    ['label' => 'Support Inbox', 'description' => 'Open and escalated customer conversations', 'href' => route('admin.support.index'), 'active' => request()->routeIs('admin.support.*'), 'badge' => $adminBadge('support')],
                ],
            ],
            [
                'label' => 'System',
                'icon' => 'settings',
                'active' => request()->routeIs('admin.webhook-deliveries.*') || request()->routeIs('admin.audit-logs.*') || request()->routeIs('admin.maintenance.*'),
                'items' => [
                    ['label' => 'Webhook Deliveries', 'description' => 'Delivery attempts and failures', 'href' => route('admin.webhook-deliveries.index'), 'active' => request()->routeIs('admin.webhook-deliveries.*'), 'badge' => $adminBadge('webhooks')],
                    ['label' => 'Audit Logs', 'description' => 'Admin and system activity trail', 'href' => route('admin.audit-logs.index'), 'active' => request()->routeIs('admin.audit-logs.*')],
                    ['label' => 'Maintenance Windows', 'description' => 'Schedule and cancel maintenance', 'href' => route('admin.maintenance.index'), 'active' => request()->routeIs('admin.maintenance.*')],
                ],
            ],
            [
                'label' => 'Catalog',
                'icon' => 'tag',
                'active' => request()->routeIs('admin.categories.*') || request()->routeIs('admin.brands.*') || request()->routeIs('admin.tags.*') || request()->routeIs('admin.attributes.*'),
                'items' => [
                    ['label' => 'Categories', 'description' => 'Category tree, banners, and featured slots', 'href' => route('admin.categories.index'), 'active' => request()->routeIs('admin.categories.*')],
                    ['label' => 'Brands', 'description' => 'Brand directory management', 'href' => route('admin.brands.index'), 'active' => request()->routeIs('admin.brands.*')],
                    ['label' => 'Tags', 'description' => 'Tag cleanup and merge tools', 'href' => route('admin.tags.index'), 'active' => request()->routeIs('admin.tags.*')],
                    ['label' => 'Attributes', 'description' => 'Category attribute definitions', 'href' => route('admin.attributes.index'), 'active' => request()->routeIs('admin.attributes.*')],
                ],
            ],
        ] : [];

        $sellerBadgeCounts = [];

        if ($isSellerNavigation) {
            $sellerBadgeCounts = \Illuminate\Support\Facades\Cache::remember('seller:navigation:badge-counts:'.$authUser->id, 30, fn () => [
                'messages' => \App\Models\Conversation::query()
                    ->where('seller_id', $authUser->id)
                    ->where(function ($readQuery) {
                        $readQuery->whereNull('seller_read_at')
                            ->orWhereColumn('seller_read_at', '<', 'last_message_at');
                    })->count(),
                'drafts' => \App\Models\Auction::query()
                    ->where('user_id', $authUser->id)
                    ->where('status', \App\Models\Auction::STATUS_DRAFT)
                    ->count(),
                'ending_soon' => \App\Models\Auction::query()
                    ->where('user_id', $authUser->id)
                    ->where('status', \App\Models\Auction::STATUS_ACTIVE)
                    ->whereBetween('end_time', [now(), now()->addDay()])
                    ->count(),
                'questions' => \App\Models\AuctionQuestion::query()
                    ->whereNull('answer')
                    ->whereHas('auction', fn ($auctionQuery) => $auctionQuery->where('user_id', $authUser->id))
                    ->count(),
            ]);
        }

        $sellerBadge = fn (string $key): ?string => ($sellerBadgeCounts[$key] ?? 0) > 0
            ? (($sellerBadgeCounts[$key] ?? 0) > 99 ? '99+' : (string) $sellerBadgeCounts[$key])
            : null;

        $sellerGroups = $isSellerNavigation ? [
            [
                'label' => 'Listings',
                'active' => request()->routeIs('seller.auctions.schedule') || request()->routeIs('seller.auctions.import') || (request()->routeIs('seller.auctions.index') && in_array(request('status'), [\App\Models\Auction::STATUS_DRAFT, \App\Models\Auction::STATUS_ACTIVE], true)),
                'items' => [
                    ['label' => 'Auction Schedule', 'description' => 'Calendar view for active and upcoming auctions', 'href' => route('seller.auctions.schedule'), 'active' => request()->routeIs('seller.auctions.schedule')],
                    ['label' => 'Import Listings', 'description' => 'Bulk create auctions from CSV', 'href' => route('seller.auctions.import'), 'active' => request()->routeIs('seller.auctions.import')],
                    ['label' => 'Draft Auctions', 'description' => 'Finish unpublished listings', 'href' => route('seller.auctions.index', ['status' => \App\Models\Auction::STATUS_DRAFT]), 'active' => request()->routeIs('seller.auctions.index') && request('status') === \App\Models\Auction::STATUS_DRAFT, 'badge' => $sellerBadge('drafts')],
                    ['label' => 'Ending Soon', 'description' => 'Active auctions closing in the next day', 'href' => route('seller.auctions.index', ['status' => \App\Models\Auction::STATUS_ACTIVE]), 'active' => request()->routeIs('seller.auctions.index') && request('status') === \App\Models\Auction::STATUS_ACTIVE, 'badge' => $sellerBadge('ending_soon')],
                ],
            ],
            [
                'label' => 'Performance',
                'active' => request()->routeIs('seller.analytics.*') || request()->routeIs('seller.auctions.insights') || request()->routeIs('sellers.leaderboard'),
                'items' => [
                    ['label' => 'Analytics', 'description' => 'Traffic, bids, and conversion reports', 'href' => route('seller.analytics.index'), 'active' => request()->routeIs('seller.analytics.*')],
                    ['label' => 'Seller Leaderboard', 'description' => 'Compare public seller performance', 'href' => route('sellers.leaderboard'), 'active' => request()->routeIs('sellers.leaderboard')],
                    ['label' => 'Buyer Questions', 'description' => 'Unanswered auction questions need replies', 'href' => route('seller.auctions.index'), 'active' => false, 'badge' => $sellerBadge('questions')],
                ],
            ],
            [
                'label' => 'Finance',
                'active' => request()->routeIs('seller.revenue.*') || request()->routeIs('seller.tax-documents.*'),
                'items' => [
                    ['label' => 'Revenue', 'description' => 'Sales, proceeds, and export tools', 'href' => route('seller.revenue.index'), 'active' => request()->routeIs('seller.revenue.*')],
                    ['label' => 'Tax Documents', 'description' => 'Generate and download seller tax PDFs', 'href' => route('seller.tax-documents.index'), 'active' => request()->routeIs('seller.tax-documents.*')],
                ],
            ],
            [
                'label' => 'Store',
                'active' => request()->routeIs('seller.storefront.*'),
                'items' => [
                    ['label' => 'Storefront Settings', 'description' => 'Profile, policies, avatar, and seller details', 'href' => route('seller.storefront.edit'), 'active' => request()->routeIs('seller.storefront.*')],
                ],
            ],
        ] : [];
    @endphp
    @php($selectedDisplayCurrency = display_currency())
    @php($supportedDisplayCurrencies = config('auction.supported_currencies', ['USD']))
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20 items-center">
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center mr-8">
                    <a href="{{ $dashboardRoute }}" class="group inline-flex border-none outline-none">
                        <x-application-logo class="h-10 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden min-w-0 space-x-2 lg:space-x-3 sm:flex items-center">
                    <x-nav-link :href="$dashboardRoute" :active="$isStaffNavigation ? request()->routeIs('admin.dashboard') : request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    @unless($isStaffNavigation || $isSellerNavigation)
                        <x-nav-link :href="route('auctions.index')" :active="request()->routeIs('auctions.index')">
                            {{ __('Browse Auctions') }}
                        </x-nav-link>
                    @endunless

                    @auth
                    @if($authUser->isStaff())
                        <div class="xl:hidden">
                            <x-dropdown align="left" width="w-[34rem]" contentClasses="p-3 bg-white">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-transparent text-sm font-semibold leading-5 text-gray-600 hover:text-brand hover:bg-brand-soft focus:outline-none transition duration-150 ease-in-out">
                                        <span>{{ __('Admin') }}</span>
                                        @if(collect($adminGroups)->contains(fn ($group) => collect($group['items'])->contains(fn ($item) => ! empty($item['badge']))))
                                            <span class="inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                        @endif
                                        <svg class="-mr-0.5 h-4 w-4 text-current/70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        @foreach($adminGroups as $group)
                                            <div>
                                                <div class="mb-1 px-2 text-xs font-bold uppercase tracking-wider {{ $group['active'] ? 'text-brand' : 'text-gray-400' }}">{{ __($group['label']) }}</div>
                                                <div class="space-y-1">
                                                    @foreach($group['items'] as $item)
                                                        <a href="{{ $item['href'] }}" class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 text-sm font-semibold transition {{ $item['active'] ? 'bg-brand-soft text-brand' : 'text-gray-700 hover:bg-brand-soft hover:text-brand' }}">
                                                            <span>{{ __($item['label']) }}</span>
                                                            @if(! empty($item['badge']))
                                                                <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">{{ $item['badge'] }}</span>
                                                            @endif
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </x-slot>
                            </x-dropdown>
                        </div>

                        <div class="hidden xl:flex items-center space-x-2">
                            @foreach($adminGroups as $group)
                                <x-dropdown align="left" width="w-[28rem]" contentClasses="p-2 bg-white">
                                    <x-slot name="trigger">
                                        <button class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-transparent text-sm font-semibold leading-5 transition duration-150 ease-in-out focus:outline-none {{ $group['active'] ? 'bg-brand-soft text-brand' : 'text-gray-600 hover:text-brand hover:bg-brand-soft' }}">
                                            <span class="inline-flex h-5 w-5 items-center justify-center">
                                                @switch($group['icon'])
                                                    @case('chart')
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19V5m0 14h16M8 16v-5m4 5V8m4 8v-3" /></svg>
                                                        @break
                                                    @case('gavel')
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 6l4 4M5 21h8M8 13l6-6 3 3-6 6H8v-3z" /></svg>
                                                        @break
                                                    @case('shield')
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 4.5-2.8 7.7-7 9-4.2-1.3-7-4.5-7-9V7l7-4zM9 12l2 2 4-4" /></svg>
                                                        @break
                                                    @case('message')
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5m8-2a8 8 0 11-4.7-7.3L21 4l-.8 4.5A8 8 0 0121 12z" /></svg>
                                                        @break
                                                    @case('settings')
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.3 4.3l.5-1.3h2.4l.5 1.3 1.5.6 1.3-.6 1.7 1.7-.6 1.3.6 1.5 1.3.5v2.4l-1.3.5-.6 1.5.6 1.3-1.7 1.7-1.3-.6-1.5.6-.5 1.3h-2.4l-.5-1.3-1.5-.6-1.3.6-1.7-1.7.6-1.3-.6-1.5-1.3-.5V9.3l1.3-.5.6-1.5-.6-1.3 1.7-1.7 1.3.6 1.5-.6zM12 9a3 3 0 100 6 3 3 0 000-6z" /></svg>
                                                        @break
                                                    @default
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 12h10M7 17h6" /></svg>
                                                @endswitch
                                            </span>
                                            <span>{{ __($group['label']) }}</span>
                                            @if(collect($group['items'])->contains(fn ($item) => ! empty($item['badge'])))
                                                <span class="inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                            @endif
                                            <svg class="-mr-0.5 h-4 w-4 text-current/70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </x-slot>
                                    <x-slot name="content">
                                        <div class="px-3 py-2">
                                            <div class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ __($group['label']) }}</div>
                                        </div>
                                        <div class="grid gap-1 {{ count($group['items']) > 3 ? 'sm:grid-cols-2' : '' }}">
                                            @foreach($group['items'] as $item)
                                                <a href="{{ $item['href'] }}" class="group flex items-start justify-between gap-3 rounded-lg px-3 py-2.5 text-sm transition {{ $item['active'] ? 'bg-brand-soft text-brand' : 'text-gray-700 hover:bg-brand-soft hover:text-brand' }}">
                                                    <span class="min-w-0">
                                                        <span class="block font-semibold leading-5">{{ __($item['label']) }}</span>
                                                        <span class="mt-0.5 block text-xs font-medium leading-4 text-gray-500 group-hover:text-brand/80">{{ __($item['description']) }}</span>
                                                    </span>
                                                    @if(! empty($item['badge']))
                                                        <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">{{ $item['badge'] }}</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    </x-slot>
                                </x-dropdown>
                            @endforeach
                        </div>
                    @endif

                    @if($isSellerNavigation)
                        <x-nav-link :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard')">
                            {{ __('Seller Dashboard') }}
                        </x-nav-link>
                        <x-nav-link :href="route('seller.auctions.index')" :active="request()->routeIs('seller.auctions.index') && blank(request('status'))">
                            {{ __('My Auctions') }}
                        </x-nav-link>
                        <a href="{{ route('seller.auctions.create') }}" class="theme-button theme-button-primary min-h-0 px-4 py-2 text-sm">
                            {{ __('Create Auction') }}
                        </a>
                        <div>
                            <x-dropdown align="left" width="w-[28rem]" contentClasses="p-3 bg-white">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-transparent text-sm font-semibold leading-5 text-gray-600 hover:text-brand hover:bg-brand-soft focus:outline-none transition duration-150 ease-in-out">
                                        <span>{{ __('Seller Tools') }}</span>
                                        @if(collect($sellerGroups)->contains(fn ($group) => collect($group['items'])->contains(fn ($item) => ! empty($item['badge']))))
                                            <span class="inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                        @endif
                                        <svg class="-mr-0.5 h-4 w-4 text-current/70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        @foreach($sellerGroups as $group)
                                            <div>
                                                <div class="mb-1 px-2 text-xs font-bold uppercase tracking-wider {{ $group['active'] ? 'text-brand' : 'text-gray-400' }}">{{ __($group['label']) }}</div>
                                                <div class="space-y-1">
                                                    @foreach($group['items'] as $item)
                                                        <a href="{{ $item['href'] }}" class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 text-sm font-semibold transition {{ $item['active'] ? 'bg-brand-soft text-brand' : 'text-gray-700 hover:bg-brand-soft hover:text-brand' }}">
                                                            <span>{{ __($item['label']) }}</span>
                                                            @if(! empty($item['badge']))
                                                                <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">{{ $item['badge'] }}</span>
                                                            @endif
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @elseif(! $authUser->isStaff() && $authUser->hasPendingSellerApplication())
                        <span class="inline-flex items-center text-sm font-medium text-gray-400 cursor-not-allowed">
                            <svg class="w-4 h-4 mr-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ __('Application Pending') }}
                        </span>
                    @elseif(! $authUser->isStaff())
                        <x-nav-link :href="route('seller.apply.form')" :active="request()->routeIs('seller.apply.*') || request()->routeIs('seller.application.status')" class="text-indigo-600 hover:text-indigo-800">
                            {{ __('Become a Seller') }}
                        </x-nav-link>
                    @endif
                    @endauth
                </div>
            </div>

            <!-- Right Actions -->
            <div class="hidden sm:flex sm:items-center sm:gap-4">
                @unless($isStaffNavigation)
                <form method="POST" action="{{ route('preferences.currency') }}" class="flex items-center">
                    @csrf
                    <label for="display-currency-desktop" class="sr-only">Display currency</label>
                    <select id="display-currency-desktop"
                            name="currency"
                            onchange="this.form.submit()"
                            class="rounded-full border-gray-200 bg-white text-sm font-medium text-gray-700 shadow-sm focus:border-brand focus:ring-brand">
                        @foreach($supportedDisplayCurrencies as $currency)
                            <option value="{{ $currency }}" @selected($selectedDisplayCurrency === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </form>
                @endunless

                @auth
                <!-- Messages -->
                @unless($isStaffNavigation)
                    <button @click="$dispatch('open-chat')" type="button"
                       class="relative p-2 text-gray-500 hover:text-brand hover:bg-brand-soft rounded-full transition">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        @if($unreadMessageCount > 0)
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full">
                                {{ $unreadMessageCount > 99 ? '99+' : $unreadMessageCount }}
                            </span>
                        @endif
                    </button>
                @endunless

                @include('components.notification-bell')

                @unless($isStaffNavigation)
                <!-- User Dropdown Menu -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-2 border border-[var(--color-border)] text-sm font-semibold rounded-full text-gray-700 bg-white/85 hover:bg-brand-soft focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 transition-all shadow-sm">
                            <div class="w-6 h-6 rounded-full bg-brand-soft text-brand font-bold flex items-center justify-center text-xs">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                            <span class="hidden lg:block">{{ Auth::user()->name }}</span>
                            <svg class="fill-current h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @if($isStaffNavigation)
                        <x-dropdown-link :href="route('admin.dashboard')">
                            {{ __('Admin Dashboard') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('admin.audit-logs.index')">
                            {{ __('Admin Activity') }}
                        </x-dropdown-link>
                        @endif
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        @unless($isStaffNavigation)
                        <x-dropdown-link :href="route('user.bids')">
                            {{ __('My Bids') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.won-auctions')">
                            {{ __('Won Auctions') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.watchlist')">
                            {{ __('Watchlist') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.saved-searches')">
                            {{ __('Saved Searches') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.keyword-alerts')">
                            {{ __('Keyword Alerts') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.activity')">
                            {{ __('Activity') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.wallet')">
                            {{ __('Wallet') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('user.credits.index')">
                            {{ __('Credits & Power-Ups') }}
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
                        <x-dropdown-link :href="route('user.webhooks.index')">
                            {{ __('Webhooks') }}
                        </x-dropdown-link>
                        @endunless

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
                @endunless
                @else
                <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 hover:text-brand transition">{{ __('Log in') }}</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="theme-button theme-button-primary ml-4 text-sm">{{ __('Register') }}</a>
                @endif
                @endauth
            </div>

            <!-- Hamburger Button (Mobile) -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-full text-gray-500 hover:text-brand hover:bg-brand-soft focus:outline-none focus:bg-brand-soft focus:text-brand transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
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
         class="hidden sm:hidden absolute top-20 w-full bg-white/95 backdrop-blur-xl shadow-xl border-b border-[var(--color-border)]"
         :class="{'block': open, 'hidden': ! open}">
        <div class="pt-2 pb-3 space-y-1">
            @unless($isStaffNavigation)
            <form method="POST" action="{{ route('preferences.currency') }}" class="px-4 pb-2">
                @csrf
                <label for="display-currency-mobile" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Display Currency</label>
                <select id="display-currency-mobile"
                        name="currency"
                        onchange="this.form.submit()"
                        class="w-full rounded-lg border-gray-200 bg-white text-sm font-medium text-gray-700 shadow-sm focus:border-brand focus:ring-brand">
                    @foreach($supportedDisplayCurrencies as $currency)
                        <option value="{{ $currency }}" @selected($selectedDisplayCurrency === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </form>
            @endunless

            <x-responsive-nav-link :href="$dashboardRoute" :active="$isStaffNavigation ? request()->routeIs('admin.dashboard') : request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            @unless($isStaffNavigation)
                <x-responsive-nav-link :href="route('auctions.index')" :active="request()->routeIs('auctions.index')">
                    {{ __('Browse Auctions') }}
                </x-responsive-nav-link>
            @endunless

            @auth
            @if($authUser->isStaff())
                <div class="pt-4 pb-2">
                    <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Admin Portal</div>
                    @foreach($adminGroups as $group)
                        <div class="mt-3 first:mt-0">
                            <div class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider {{ $group['active'] ? 'text-brand' : 'text-gray-400' }}">
                                {{ __($group['label']) }}
                                @if(collect($group['items'])->contains(fn ($item) => ! empty($item['badge'])))
                                    <span class="ml-2 inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                @endif
                            </div>
                            @foreach($group['items'] as $item)
                                <x-responsive-nav-link :href="$item['href']" :active="$item['active']">
                                    <span class="flex items-center justify-between gap-3">
                                        <span>{{ __($item['label']) }}</span>
                                        @if(! empty($item['badge']))
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">{{ $item['badge'] }}</span>
                                        @endif
                                    </span>
                                </x-responsive-nav-link>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif

            @if($isSellerNavigation)
                <div class="pt-4 pb-2">
                    <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Seller Portal</div>
                    <x-responsive-nav-link :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard')">
                        {{ __('Seller Dashboard') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.auctions.index')" :active="request()->routeIs('seller.auctions.index') && blank(request('status'))">
                        {{ __('My Auctions') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.auctions.create')" :active="request()->routeIs('seller.auctions.create')">
                        {{ __('Create Auction') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.messages.index')" :active="request()->routeIs('seller.messages.*')">
                        <span class="flex items-center justify-between gap-3">
                            <span>{{ __('Messages') }}</span>
                            @if($sellerBadge('messages'))
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">{{ $sellerBadge('messages') }}</span>
                            @endif
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('seller.analytics.index')" :active="request()->routeIs('seller.analytics.*')">
                        {{ __('Analytics') }}
                    </x-responsive-nav-link>

                    @foreach($sellerGroups as $group)
                        <div class="mt-3 first:mt-0">
                            <div class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider {{ $group['active'] ? 'text-brand' : 'text-gray-400' }}">
                                {{ __($group['label']) }}
                                @if(collect($group['items'])->contains(fn ($item) => ! empty($item['badge'])))
                                    <span class="ml-2 inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                @endif
                            </div>
                            @foreach($group['items'] as $item)
                                <x-responsive-nav-link :href="$item['href']" :active="$item['active']">
                                    <span class="flex items-center justify-between gap-3">
                                        <span>{{ __($item['label']) }}</span>
                                        @if(! empty($item['badge']))
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">{{ $item['badge'] }}</span>
                                        @endif
                                    </span>
                                </x-responsive-nav-link>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @elseif(! $authUser->isStaff() && $authUser->hasPendingSellerApplication())
                <div class="px-4 py-3 text-sm text-amber-600 bg-amber-50 font-medium">{{ __('Application Pending') }}</div>
            @elseif(! $authUser->isStaff())
                <x-responsive-nav-link :href="route('seller.apply.form')" :active="request()->routeIs('seller.apply.*') || request()->routeIs('seller.application.status')">{{ __('Become a Seller') }}</x-responsive-nav-link>
            @endif
            @endauth
        </div>

        @auth
        @unless($isStaffNavigation)
        <div class="pt-4 pb-4 border-t border-gray-200">
            <div class="px-4 flex items-center mb-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-brand-soft text-brand font-bold flex items-center justify-center text-lg">
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
                @if($isStaffNavigation)
                <x-responsive-nav-link :href="route('admin.dashboard')">
                    {{ __('Admin Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.audit-logs.index')">
                    {{ __('Admin Activity') }}
                </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                @unless($isStaffNavigation)
                <x-responsive-nav-link :href="route('user.bids')">
                    {{ __('My Bids') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.won-auctions')">
                    {{ __('Won Auctions') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.watchlist')">
                    {{ __('Watchlist') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.saved-searches')">
                    {{ __('Saved Searches') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.keyword-alerts')">
                    {{ __('Keyword Alerts') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.activity')">
                    {{ __('Activity') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.wallet')">
                    {{ __('Wallet') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.credits.index')">
                    {{ __('Credits & Power-Ups') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.referrals')">
                    {{ __('Referrals') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.invoices')">
                    {{ __('Invoices') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.notification-preferences')">
                    {{ __('Notification Settings') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.api-tokens.index')">
                    {{ __('API Tokens') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.webhooks.index')">
                    {{ __('Webhooks') }}
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
                @endunless
                
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
        @endunless
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
    @unless($isStaffNavigation)
        <x-chat-drawer />
    @endunless
    @endauth
</nav>
