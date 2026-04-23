<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Analytics Reports</h2>
                <p class="text-sm text-gray-500">Category performance, peak bidding times, seller rankings, and buyer activity.</p>
            </div>
            <div class="flex gap-2">
                <select id="analytics-period" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 90 days</option>
                </select>
                <button id="refresh-analytics" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Refresh</button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-700">Advanced exports</p>
                        <p class="mt-1 text-sm text-indigo-900">Download CSV snapshots for deeper analysis or offline reporting.</p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <a data-analytics-export="categories" href="{{ route('admin.analytics.export', ['report' => 'categories', 'days' => 30]) }}" class="rounded-xl bg-white px-4 py-2 text-center text-sm font-semibold text-slate-800 shadow-sm hover:text-indigo-700">Export Categories</a>
                        <a data-analytics-export="bid-timing" href="{{ route('admin.analytics.export', ['report' => 'bid-timing', 'days' => 30]) }}" class="rounded-xl bg-white px-4 py-2 text-center text-sm font-semibold text-slate-800 shadow-sm hover:text-indigo-700">Export Heatmap</a>
                        <a data-analytics-export="leaderboard" href="{{ route('admin.analytics.export', ['report' => 'leaderboard', 'period' => 30]) }}" class="rounded-xl bg-white px-4 py-2 text-center text-sm font-semibold text-slate-800 shadow-sm hover:text-indigo-700">Export Sellers</a>
                        <a data-analytics-export="buyers" href="{{ route('admin.analytics.export', ['report' => 'buyers', 'days' => 30]) }}" class="rounded-xl bg-white px-4 py-2 text-center text-sm font-semibold text-slate-800 shadow-sm hover:text-indigo-700">Export Buyers</a>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[1.4fr,1fr]">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Category Performance</h3>
                            <p class="text-sm text-gray-500">Auction count, sell-through, average price, and demand signals by category.</p>
                        </div>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Category</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Sales</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Sell-through</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Avg Price</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Appreciation</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Bids</th>
                                </tr>
                            </thead>
                            <tbody id="category-analytics-body" class="divide-y divide-gray-100">
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Loading category analytics...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Best Listing Time</h3>
                    <p class="text-sm text-gray-500">Heatmap of bid activity by day and hour. Darker cells mean stronger bid demand.</p>
                    <div id="peak-recommendation" class="mt-4 rounded-xl bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-800">
                        Loading recommendation...
                    </div>
                    <div class="mt-4 overflow-x-auto pb-2" data-analytics-heatmap-scroll>
                        <div id="bid-heatmap" class="grid min-w-[56rem] grid-cols-8 gap-2 text-xs"></div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Seller Leaderboard</h3>
                <p class="text-sm text-gray-500">Top sellers ranked by revenue, sales count, and marketplace reputation.</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Rank</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Seller</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Revenue</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Sales</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Rating</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Active Listings</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Bids Received</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboard-body" class="divide-y divide-gray-100">
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Loading leaderboard...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Buyer Activity</h3>
                        <p class="text-sm text-gray-500">Admin view of buyer bidding and spend patterns for coaching and fraud review.</p>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Buyer</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Bids</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Wins</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Wallet</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="buyer-analytics-body" class="divide-y divide-gray-100">
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Loading buyer activity...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const periodSelect = document.getElementById('analytics-period');
                const refreshBtn = document.getElementById('refresh-analytics');
                const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                const categoryBody = document.getElementById('category-analytics-body');
                const leaderboardBody = document.getElementById('leaderboard-body');
                const buyerBody = document.getElementById('buyer-analytics-body');
                const peakRecommendation = document.getElementById('peak-recommendation');
                const heatmap = document.getElementById('bid-heatmap');
                const exportLinks = document.querySelectorAll('[data-analytics-export]');

                const formatMoney = (value) => '$' + Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                const intensityClass = (value, max) => {
                    const ratio = max > 0 ? value / max : 0;
                    if (ratio >= 0.8) return 'bg-indigo-700 text-white';
                    if (ratio >= 0.6) return 'bg-indigo-500 text-white';
                    if (ratio >= 0.4) return 'bg-indigo-300 text-indigo-950';
                    if (ratio >= 0.2) return 'bg-indigo-100 text-indigo-900';
                    return 'bg-gray-100 text-gray-500';
                };

                const skeletonRows = (columns, count = 4) => Array.from({ length: count }, () => `
                    <tr>
                        <td colspan="${columns}" class="px-4 py-3">
                            <div class="h-4 w-full animate-pulse rounded bg-gray-200"></div>
                        </td>
                    </tr>
                `).join('');

                const loadingState = () => {
                    categoryBody.innerHTML = skeletonRows(6);
                    leaderboardBody.innerHTML = skeletonRows(7);
                    buyerBody.innerHTML = skeletonRows(5);
                    peakRecommendation.innerHTML = '<div class="h-4 w-2/3 animate-pulse rounded bg-indigo-200"></div>';
                    heatmap.innerHTML = Array.from({ length: 40 }, () => '<div class="h-8 animate-pulse rounded-md bg-gray-200"></div>').join('');
                    heatmap.style.gridTemplateColumns = 'repeat(8, minmax(0, 1fr))';
                };

                async function loadCategoryAnalytics(days) {
                    const response = await fetch(`{{ route('admin.analytics.categories') }}?days=${days}`, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    const rows = payload.data ?? [];
                    if (!rows.length) {
                        categoryBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No category analytics available.</td></tr>';
                        return;
                    }

                    categoryBody.innerHTML = rows.map((row) => `
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">${row.category?.name ?? 'Unknown'}</td>
                            <td class="px-4 py-3 text-right">${Number(row.total_sales || 0).toLocaleString()}</td>
                            <td class="px-4 py-3 text-right">${(Number(row.sell_through_rate || 0) * 100).toFixed(1)}%</td>
                            <td class="px-4 py-3 text-right">${formatMoney(row.avg_price)}</td>
                            <td class="px-4 py-3 text-right">${Number(row.avg_appreciation || 0).toFixed(2)}%</td>
                            <td class="px-4 py-3 text-right">${Number(row.total_bids || 0).toLocaleString()}</td>
                        </tr>
                    `).join('');
                }

                async function loadBidTiming(days) {
                    const response = await fetch(`{{ route('admin.analytics.bid-timing') }}?days=${days}`, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    peakRecommendation.textContent = payload.recommendation ?? 'Insufficient data';

                    const data = payload.heatmap ?? [];
                    const maxBids = Math.max(0, ...data.map((item) => Number(item.total_bids || 0)));
                    const lookup = new Map(data.map((item) => [`${item.day_of_week}-${item.hour_of_day}`, item]));

                    let html = '<div></div>';
                    for (let hour = 0; hour < 24; hour++) {
                        html += `<div class="text-center font-semibold text-gray-500">${hour}</div>`;
                    }

                    dayOrder.forEach((day) => {
                        html += `<div class="flex items-center font-semibold capitalize text-gray-600">${day.slice(0, 3)}</div>`;
                        for (let hour = 0; hour < 24; hour++) {
                            const item = lookup.get(`${day}-${hour}`) ?? { total_bids: 0 };
                            html += `<div class="rounded-md px-1 py-2 text-center ${intensityClass(Number(item.total_bids || 0), maxBids)}" title="${day} ${hour}:00 — ${Number(item.total_bids || 0)} bids">${Number(item.total_bids || 0)}</div>`;
                        }
                    });

                    heatmap.style.gridTemplateColumns = '72px repeat(24, minmax(0, 1fr))';
                    heatmap.innerHTML = html;
                }

                async function loadLeaderboard(period) {
                    const response = await fetch(`{{ route('admin.analytics.leaderboard') }}?period=${period}`, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    const rows = payload.data ?? [];
                    if (!rows.length) {
                        leaderboardBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No seller analytics available.</td></tr>';
                        return;
                    }

                    leaderboardBody.innerHTML = rows.map((row, index) => `
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-900">#${index + 1}</td>
                            <td class="px-4 py-3 text-gray-900">${row.user?.name ?? 'Unknown'}</td>
                            <td class="px-4 py-3 text-right">${formatMoney(row.total_revenue)}</td>
                            <td class="px-4 py-3 text-right">${Number(row.total_sales || 0).toLocaleString()}</td>
                            <td class="px-4 py-3 text-right">${Number(row.avg_rating || 0).toFixed(2)}</td>
                            <td class="px-4 py-3 text-right">${Number(row.active_listings || 0).toLocaleString()}</td>
                            <td class="px-4 py-3 text-right">${Number(row.total_bids_received || 0).toLocaleString()}</td>
                        </tr>
                    `).join('');
                }

                async function loadBuyerAnalytics(days) {
                    const response = await fetch(`{{ route('admin.analytics.buyers') }}?days=${days}`, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    const rows = payload.data ?? [];
                    if (!rows.length) {
                        buyerBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No buyer activity available.</td></tr>';
                        return;
                    }

                    buyerBody.innerHTML = rows.map((row) => `
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">${row.name}</div>
                                <div class="text-xs text-gray-500">${row.email}</div>
                            </td>
                            <td class="px-4 py-3 text-right">${Number(row.total_bids || 0).toLocaleString()}</td>
                            <td class="px-4 py-3 text-right">${Number(row.auctions_won || 0).toLocaleString()}</td>
                            <td class="px-4 py-3 text-right">${formatMoney(row.wallet_balance)}</td>
                            <td class="px-4 py-3 text-right">
                                <a class="text-indigo-600 hover:text-indigo-800" href="/admin/users/${row.id}">Open profile</a>
                            </td>
                        </tr>
                    `).join('');
                }

                async function refresh() {
                    const value = periodSelect.value;
                    refreshBtn.disabled = true;
                    exportLinks.forEach((link) => {
                        const report = link.dataset.analyticsExport;
                        const key = report === 'leaderboard' ? 'period' : 'days';
                        link.href = `{{ url('/admin/analytics/export') }}/${report}?${key}=${value}`;
                    });
                    loadingState();
                    try {
                        await Promise.all([
                            loadCategoryAnalytics(value),
                            loadBidTiming(value),
                            loadLeaderboard(value),
                            loadBuyerAnalytics(value),
                        ]);
                    } catch (_) {
                        categoryBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">Unable to load category analytics.</td></tr>';
                        leaderboardBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">Unable to load leaderboard.</td></tr>';
                        buyerBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-red-500">Unable to load buyer activity.</td></tr>';
                        peakRecommendation.textContent = 'Unable to load recommendation.';
                        heatmap.innerHTML = '<div class="col-span-full rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">Unable to load bid timing heatmap.</div>';
                        heatmap.style.gridTemplateColumns = '1fr';
                    } finally {
                        refreshBtn.disabled = false;
                    }
                }

                refreshBtn.addEventListener('click', refresh);
                periodSelect.addEventListener('change', refresh);
                refresh();
            })();
        </script>
    @endpush
</x-app-layout>
