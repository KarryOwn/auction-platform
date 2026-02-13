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
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Bids / min</div>
                        <div class="text-xl font-bold" id="bids-per-min">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Bids / 5 min</div>
                        <div class="text-xl font-bold" id="bids-per-5min">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Active Bidders (1h)</div>
                        <div class="text-xl font-bold" id="active-bidders">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Ending Soon (5m)</div>
                        <div class="text-xl font-bold" id="ending-soon">-</div>
                    </div>
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
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        function refreshMetrics() {
            fetch('{{ route("admin.metrics.live") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(json => {
                const d = json.data;
                document.getElementById('bids-per-min').textContent = d.bids_last_minute;
                document.getElementById('bids-per-5min').textContent = d.bids_last_5_minutes;
                document.getElementById('active-bidders').textContent = d.unique_bidders_hour;
                document.getElementById('ending-soon').textContent = d.ending_in_5_minutes;
                document.getElementById('last-updated').textContent = 'Updated: ' + new Date(d.timestamp).toLocaleTimeString();
            })
            .catch(() => {});
        }
        refreshMetrics();
        setInterval(refreshMetrics, 10000);
    </script>
    @endpush
</x-app-layout>