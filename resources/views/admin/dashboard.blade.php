<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Admin Monitor
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Total Users</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_users']) }}</div>
                    @if($stats['banned_users'] > 0)
                        <div class="text-sm text-red-500 mt-1">{{ $stats['banned_users'] }} banned</div>
                    @endif
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Active Auctions</div>
                    <div class="text-3xl font-bold text-green-600 mt-1">{{ number_format($stats['active_auctions']) }}</div>
                    <div class="text-sm text-gray-400 mt-1">{{ $stats['completed_auctions'] }} completed</div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Bids Today</div>
                    <div class="text-3xl font-bold text-blue-600 mt-1">{{ number_format($stats['total_bids_today']) }}</div>
                    <div class="text-sm text-gray-400 mt-1">{{ $stats['total_bids_hour'] }} in last hour</div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Pending Reports</div>
                    <div class="text-3xl font-bold {{ $stats['pending_reports'] > 0 ? 'text-red-600' : 'text-gray-900' }} mt-1">
                        {{ $stats['pending_reports'] }}
                    </div>
                </div>
            </div>

            {{-- Live Metrics Panel --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6" id="live-metrics">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Live Metrics</h3>
                    <span class="text-xs text-gray-400" id="last-updated">Loading...</span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-1">
                            <div class="text-xs text-gray-500">Bids / min</div>
                            <span id="trend-bids-per-min" class="text-xs text-gray-400">→</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="bids-per-min">-</div>
                        <div class="mt-2 h-10">
                            <canvas id="spark-bids-per-min" class="w-full h-10"></canvas>
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-1">
                            <div class="text-xs text-gray-500">Bids / 5 min</div>
                            <span id="trend-bids-per-5min" class="text-xs text-gray-400">→</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="bids-per-5min">-</div>
                        <div class="mt-2 h-10">
                            <canvas id="spark-bids-per-5min" class="w-full h-10"></canvas>
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-1">
                            <div class="text-xs text-gray-500">Active Bidders (1h)</div>
                            <span id="trend-active-bidders" class="text-xs text-gray-400">→</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="active-bidders">-</div>
                        <div class="mt-2 h-10">
                            <canvas id="spark-active-bidders" class="w-full h-10"></canvas>
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-1">
                            <div class="text-xs text-gray-500">Ending Soon (5m)</div>
                            <span id="trend-ending-soon" class="text-xs text-gray-400">→</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="ending-soon">-</div>
                        <div class="mt-2 h-10">
                            <canvas id="spark-ending-soon" class="w-full h-10"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fraud Alerts --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Fraud Alerts</h3>
                    <span class="text-xs text-gray-400">Last 2 hours</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-200">
                                <th class="py-2 pr-4">Auction</th>
                                <th class="py-2 pr-4">Severity</th>
                                <th class="py-2 pr-4">Detail</th>
                                <th class="py-2">Detected</th>
                            </tr>
                        </thead>
                        <tbody id="fraud-alerts-body">
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-400">Loading alerts...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Quick Links --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('admin.auctions.index') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition block">
                    <h3 class="text-lg font-semibold text-gray-800">Manage Auctions</h3>
                    <p class="text-sm text-gray-500 mt-1">View, cancel, extend auctions. Detect suspicious activity.</p>
                </a>
                <a href="{{ route('admin.users.index') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition block">
                    <h3 class="text-lg font-semibold text-gray-800">Manage Users</h3>
                    <p class="text-sm text-gray-500 mt-1">Search users, ban/unban, change roles.</p>
                </a>
                <a href="{{ route('admin.reports.index') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition block">
                    <h3 class="text-lg font-semibold text-gray-800">Review Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $stats['pending_reports'] }} pending report(s) to review.</p>
                </a>
                <a href="{{ route('admin.audit-logs.index') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition block">
                    <h3 class="text-lg font-semibold text-gray-800">Audit Logs</h3>
                    <p class="text-sm text-gray-500 mt-1">Review all admin actions and changes.</p>
                </a>
                <a href="{{ route('admin.maintenance.index') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition block">
                    <h3 class="text-lg font-semibold text-gray-800">Maintenance Windows</h3>
                    <p class="text-sm text-gray-500 mt-1">Schedule downtime, announce updates, and keep the bypass link handy.</p>
                </a>
                <a href="{{ route('admin.analytics.index') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition block">
                    <h3 class="text-lg font-semibold text-gray-800">Analytics Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">Category trends, peak bidding windows, seller rankings, and buyer activity.</p>
                </a>
                <a href="{{ route('admin.support.index') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition block">
                    <h3 class="text-lg font-semibold text-gray-800">Support Inbox</h3>
                    <p class="text-sm text-gray-500 mt-1">Review escalations, reply as staff, and close resolved support chats.</p>
                </a>
            </div>

        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        const history = {
            bids_per_min: [],
            bids_per_5min: [],
            active_bidders: [],
            ending_soon: [],
        };

        const chartState = {};
        const metricMap = {
            bids_per_min: {
                valueId: 'bids-per-min',
                trendId: 'trend-bids-per-min',
                canvasId: 'spark-bids-per-min',
                sourceKey: 'bids_last_minute',
                color: '#2563eb',
            },
            bids_per_5min: {
                valueId: 'bids-per-5min',
                trendId: 'trend-bids-per-5min',
                canvasId: 'spark-bids-per-5min',
                sourceKey: 'bids_last_5_minutes',
                color: '#4f46e5',
            },
            active_bidders: {
                valueId: 'active-bidders',
                trendId: 'trend-active-bidders',
                canvasId: 'spark-active-bidders',
                sourceKey: 'unique_bidders_hour',
                color: '#059669',
            },
            ending_soon: {
                valueId: 'ending-soon',
                trendId: 'trend-ending-soon',
                canvasId: 'spark-ending-soon',
                sourceKey: 'ending_in_5_minutes',
                color: '#d97706',
            },
        };

        function ensureSparkline(metricKey, metricConfig) {
            if (!window.Chart || chartState[metricKey]) {
                return;
            }

            const canvas = document.getElementById(metricConfig.canvasId);
            if (!canvas) {
                return;
            }

            chartState[metricKey] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        borderColor: metricConfig.color,
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0.4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    resizeDelay: 100,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false },
                    },
                    scales: {
                        x: { display: false, grid: { display: false } },
                        y: { display: false, grid: { display: false } },
                    },
                },
            });
        }

        function updateTrendIndicator(elementId, values) {
            const indicator = document.getElementById(elementId);
            if (!indicator) {
                return;
            }

            if (values.length < 2) {
                indicator.textContent = '→';
                indicator.className = 'text-xs text-gray-400';
                return;
            }

            const previous = values[values.length - 2];
            const current = values[values.length - 1];

            if (current > previous) {
                indicator.textContent = '↑';
                indicator.className = 'text-xs text-green-600';
                return;
            }

            if (current < previous) {
                indicator.textContent = '↓';
                indicator.className = 'text-xs text-red-600';
                return;
            }

            indicator.textContent = '→';
            indicator.className = 'text-xs text-gray-400';
        }

        function pushMetricHistory(metricKey, value) {
            history[metricKey].push(Number(value));
            if (history[metricKey].length > 12) {
                history[metricKey].shift();
            }
        }

        function renderSparklines() {
            Object.entries(metricMap).forEach(([metricKey, metricConfig]) => {
                ensureSparkline(metricKey, metricConfig);

                const chart = chartState[metricKey];
                if (!chart) {
                    return;
                }

                chart.data.labels = history[metricKey].map((_, index) => index + 1);
                chart.data.datasets[0].data = [...history[metricKey]];
                chart.update();

                updateTrendIndicator(metricConfig.trendId, history[metricKey]);
            });
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');
        }

        function severityBadge(severity) {
            const normalized = String(severity || 'warning').toLowerCase();

            if (normalized === 'critical') {
                return '<span class="inline-flex items-center font-medium rounded-full text-xs px-2.5 py-0.5 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">critical</span>';
            }

            if (normalized === 'high') {
                return '<span class="inline-flex items-center font-medium rounded-full text-xs px-2.5 py-0.5 bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300">high</span>';
            }

            return '<span class="inline-flex items-center font-medium rounded-full text-xs px-2.5 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">warning</span>';
        }

        function renderFraudAlerts(alerts) {
            const body = document.getElementById('fraud-alerts-body');
            if (!body) {
                return;
            }

            if (!alerts || alerts.length === 0) {
                body.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-400">No recent alerts</td></tr>';
                return;
            }

            body.innerHTML = alerts.map((alert) => {
                const auctionText = alert.auction_title
                    ? `#${escapeHtml(alert.auction_id)} - ${escapeHtml(alert.auction_title)}`
                    : `#${escapeHtml(alert.auction_id)}`;

                return `
                    <tr class="border-b border-gray-100 last:border-b-0">
                        <td class="py-2 pr-4 text-gray-700">${auctionText}</td>
                        <td class="py-2 pr-4">${severityBadge(alert.severity)}</td>
                        <td class="py-2 pr-4 text-gray-700">${escapeHtml(alert.detail || '-')}</td>
                        <td class="py-2 text-gray-500">${new Date(alert.detected_at).toLocaleTimeString()}</td>
                    </tr>
                `;
            }).join('');
        }

        function refreshMetrics() {
            return fetch('{{ route("admin.metrics.live") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then((r) => r.json())
                .then((json) => {
                    const d = json.data;

                    Object.entries(metricMap).forEach(([metricKey, metricConfig]) => {
                        const value = d[metricConfig.sourceKey] ?? 0;
                        pushMetricHistory(metricKey, value);

                        const valueNode = document.getElementById(metricConfig.valueId);
                        if (valueNode) {
                            valueNode.textContent = value;
                        }
                    });

                    renderSparklines();

                    const lastUpdated = document.getElementById('last-updated');
                    if (lastUpdated) {
                        lastUpdated.textContent = 'Updated: ' + new Date(d.timestamp).toLocaleTimeString();
                    }
                })
                .catch(() => {});
        }

        function refreshFraudAlerts() {
            return fetch('{{ route("admin.metrics.fraud-alerts") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then((r) => r.json())
                .then((json) => {
                    renderFraudAlerts(json.data || []);
                })
                .catch(() => {
                    renderFraudAlerts([]);
                });
        }

        function refreshDashboardLiveData() {
            refreshMetrics();
            refreshFraudAlerts();
        }

        Object.entries(metricMap).forEach(([metricKey, metricConfig]) => {
            ensureSparkline(metricKey, metricConfig);
            renderSparklines();
        });

        if (window.adminMetricsInterval) {
            clearInterval(window.adminMetricsInterval);
        }

        refreshDashboardLiveData();
        window.adminMetricsInterval = setInterval(refreshDashboardLiveData, 10000);
    </script>
    @endpush
</x-app-layout>
