<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Seller Dashboard</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('seller.auctions.schedule') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-gray-200 text-gray-700 dark:text-gray-200 dark:border-gray-700 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">Auction Schedule</a>
                <a href="{{ route('seller.auctions.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">Create New Auction</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <x-ui.card>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Gross Revenue (This Month)</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($stats['total_revenue'], 2) }}</p>
                </x-ui.card>

                <x-ui.card>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Active Listings</p>
                    <div class="mt-2 flex items-center justify-between">
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100" id="m-active">{{ $stats['active_auctions'] }}</p>
                        <a href="{{ route('seller.auctions.index', ['status' => 'active']) }}" class="text-sm text-indigo-600 hover:text-indigo-700">View all</a>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Bids Received (Today)</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100" id="m-bids-today">{{ $stats['bids_today'] }}</p>
                </x-ui.card>

                <x-ui.card>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Conversion Rate</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['conversion_rate'], 2) }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $stats['completed_auctions'] }} completed of {{ $stats['total_auctions'] }} total</p>
                </x-ui.card>
            </div>

            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Vacation Mode</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pause active auctions or leave a buyer-facing message while you are away.</p>
                        </div>
                        @if(auth()->user()->vacation_mode)
                            <x-ui.badge color="amber">Active</x-ui.badge>
                        @else
                            <x-ui.badge color="green">Available</x-ui.badge>
                        @endif
                    </div>
                </x-slot:header>

                <div class="grid gap-6 lg:grid-cols-[1.3fr,0.7fr]">
                    @if(auth()->user()->vacation_mode)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                            <p class="text-sm font-semibold text-amber-900">Vacation mode is active.</p>
                            <p class="mt-2 text-sm text-amber-800">Buyer actions are paused on eligible auctions while you are away.</p>
                            @if(auth()->user()->vacation_mode_message)
                                <p class="mt-3 rounded-xl bg-white/70 px-3 py-2 text-sm text-amber-900">
                                    "{{ auth()->user()->vacation_mode_message }}"
                                </p>
                            @endif
                            @if(auth()->user()->vacation_mode_ends_at)
                                <p class="mt-3 text-xs text-amber-700">
                                    Expected return: {{ auth()->user()->vacation_mode_ends_at->format('M d, Y H:i') }}
                                </p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('seller.vacation.deactivate') }}" class="flex items-start justify-end">
                            @csrf
                            <x-primary-button>Return From Vacation</x-primary-button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('seller.vacation.activate') }}" class="grid gap-4 lg:grid-cols-2">
                            @csrf
                            <div class="lg:col-span-2">
                                <label for="vacation_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mode</label>
                                <select id="vacation_mode" name="mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="pause">Pause auctions and disable bidding</option>
                                    <option value="message_only">Keep auctions live but show away banner</option>
                                </select>
                            </div>
                            <div>
                                <label for="vacation_ends_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Return date (optional)</label>
                                <input id="vacation_ends_at" type="datetime-local" name="ends_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="lg:row-span-2">
                                <label for="vacation_message" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Buyer-facing message</label>
                                <textarea id="vacation_message" name="message" rows="4" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Example: I am away until Monday. Orders and replies will resume when I return."></textarea>
                            </div>
                            <div class="lg:col-span-2">
                                <x-primary-button>Activate Vacation Mode</x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Live Listings</h3>
                        <a href="{{ route('seller.auctions.index', ['status' => 'active']) }}" class="text-sm text-indigo-600 hover:text-indigo-700">Manage listings</a>
                    </div>
                </x-slot:header>

                @if($activeListings->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No active listings yet. Publish a draft auction to start receiving bids.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <th class="py-3 pr-3">Thumbnail</th>
                                    <th class="py-3 pr-3">Title</th>
                                    <th class="py-3 pr-3">Current Price</th>
                                    <th class="py-3 pr-3">Bids</th>
                                    <th class="py-3 pr-3">Ends</th>
                                    <th class="py-3 pr-3">Health</th>
                                    <th class="py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activeListings as $auction)
                                    @php
                                        $health = $auction->bids_count > 5
                                            ? ['label' => 'Hot', 'dot' => 'bg-green-500', 'text' => 'text-green-700 dark:text-green-400']
                                            : ($auction->bids_count >= 1
                                                ? ['label' => 'Active', 'dot' => 'bg-blue-500', 'text' => 'text-blue-700 dark:text-blue-400']
                                                : ['label' => 'No bids', 'dot' => 'bg-red-500', 'text' => 'text-red-700 dark:text-red-400']);
                                    @endphp
                                    <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                        <td class="py-3 pr-3">
                                            @if($auction->getCoverImageUrl())
                                                <img src="{{ $auction->getCoverImageUrl() }}" class="w-12 h-12 rounded-lg object-cover" alt="{{ $auction->title }}">
                                            @else
                                                <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-gray-700"></div>
                                            @endif
                                        </td>
                                        <td class="py-3 pr-3 text-gray-900 dark:text-gray-100 font-medium max-w-xs truncate">{{ $auction->title }}</td>
                                        <td class="py-3 pr-3 text-gray-900 dark:text-gray-100">${{ number_format((float) $auction->current_price, 2) }}</td>
                                        <td class="py-3 pr-3 text-gray-700 dark:text-gray-300">{{ $auction->bids_count }}</td>
                                        <td class="py-3 pr-3 text-gray-700 dark:text-gray-300">{{ optional($auction->end_time)->diffForHumans() ?? 'N/A' }}</td>
                                        <td class="py-3 pr-3">
                                            <span class="inline-flex items-center gap-2 {{ $health['text'] }}">
                                                <span class="w-2.5 h-2.5 rounded-full {{ $health['dot'] }}"></span>
                                                {{ $health['label'] }}
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <div class="inline-flex items-center gap-3">
                                                <a href="{{ route('auctions.show', $auction) }}" class="text-indigo-600 hover:text-indigo-700">View</a>
                                                <a href="{{ route('seller.auctions.edit', $auction) }}" class="text-gray-600 hover:text-gray-700 dark:text-gray-300 dark:hover:text-gray-100">Edit</a>
                                                <a href="{{ route('seller.auctions.insights', $auction) }}" class="text-emerald-600 hover:text-emerald-700">Insights</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2">
                    <x-ui.card>
                        <x-slot:header>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Revenue (Last 30 Days)</h3>
                        </x-slot:header>

                        <div class="h-64">
                            <canvas id="seller-revenue-chart" class="w-full h-full"></canvas>
                        </div>
                    </x-ui.card>
                </div>

                <x-ui.card>
                    <x-slot:header>
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Unread Messages</h3>
                            <a href="{{ route('seller.messages.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700">Open inbox</a>
                        </div>
                    </x-slot:header>

                    @if($recentMessages->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No unread conversations right now.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($recentMessages as $conversation)
                                @php($latestMessage = $conversation->messages->first())
                                <li>
                                    <a href="{{ route('seller.messages.show', $conversation) }}" class="block rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:border-indigo-300 dark:hover:border-indigo-500 transition-colors">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $conversation->buyer?->name ?? 'Buyer' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $conversation->auction?->title ?? 'Untitled auction' }}</p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300 truncate mt-1">{{ $latestMessage?->body ?? 'No message preview available.' }}</p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>

            <div class="bg-white p-6 rounded shadow-sm">
                <h3 class="font-semibold mb-3">Recent Activity</h3>
                @if($recentActivity->isEmpty())
                    <p class="text-sm text-gray-500">No bidding activity yet.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($recentActivity as $bid)
                            <li class="text-sm text-gray-700">{{ $bid->user?->name }} bid ${{ number_format($bid->amount,2) }} on {{ $bid->auction?->title }} ({{ $bid->created_at->diffForHumans() }})</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        Echo.private('seller.{{ auth()->id() }}')
            .listen('.new.bid.on.listing', () => refreshMetrics())
            .listen('.message.sent', () => refreshMetrics())
            .listen('.auction.ended.for.seller', () => refreshMetrics());

        const revenueChartData = JSON.parse(@json($revenueChartData));
        const chartCanvas = document.getElementById('seller-revenue-chart');
        let sellerRevenueChart = null;

        if (chartCanvas && window.Chart) {
            const hasRevenue = revenueChartData.some(item => Number(item.revenue) > 0);

            sellerRevenueChart = new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: revenueChartData.map(item => item.date),
                    datasets: [{
                        label: 'Revenue',
                        data: revenueChartData.map(item => Number(item.revenue)),
                        borderColor: 'rgb(79, 70, 229)',
                        backgroundColor: 'rgba(79, 70, 229, 0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return '$' + Number(context.parsed.y || 0).toFixed(2);
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                autoSkip: true,
                                maxTicksLimit: 6,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback(value) {
                                    return '$' + Number(value).toFixed(0);
                                },
                            },
                        },
                    },
                },
                plugins: [{
                    id: 'empty-state-label',
                    afterDraw(chart) {
                        if (hasRevenue) {
                            return;
                        }

                        const { ctx, chartArea } = chart;
                        if (!chartArea) {
                            return;
                        }

                        ctx.save();
                        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-text-muted').trim() || '#9ca3af';
                        ctx.textAlign = 'center';
                        ctx.font = '13px sans-serif';
                        ctx.fillText('No revenue data in the last 30 days', (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2);
                        ctx.restore();
                    },
                }],
            });
        }

        async function refreshMetrics() {
            const res = await fetch('{{ route('seller.metrics.live') }}', { headers: { 'Accept': 'application/json' } });
            const json = await res.json();

            const unreadNode = document.getElementById('m-unread');
            const bidsTodayNode = document.getElementById('m-bids-today');
            const activeNode = document.getElementById('m-active');

            if (unreadNode) {
                unreadNode.textContent = json.unread_messages;
            }

            if (bidsTodayNode) {
                bidsTodayNode.textContent = json.bids_today;
            }

            if (activeNode) {
                activeNode.textContent = json.active_auction_count;
            }

            try {
                const chartRes = await fetch('{{ route('seller.revenue.chart-data') }}', { headers: { 'Accept': 'application/json' } });
                if (chartRes.ok && sellerRevenueChart) {
                    const chartJson = await chartRes.json();
                    sellerRevenueChart.data.labels = chartJson.map(item => item.date);
                    sellerRevenueChart.data.datasets[0].data = chartJson.map(item => Number(item.revenue));
                    sellerRevenueChart.update('active');
                }
            } catch (error) {
                console.error('Unable to refresh revenue chart data', error);
            }
        }
    </script>
    @endpush
</x-app-layout>
