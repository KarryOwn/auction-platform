<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                User #{{ $user->id }}: {{ $user->name }}
            </h2>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Users</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- User Info + Activity Stats --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- User Profile --}}
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Profile</h3>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="text-sm text-gray-900">{{ $user->name }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="text-sm text-gray-900">{{ $user->email }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Role</dt>
                        <dd>
                            @php
                                $roleColors = ['admin' => 'bg-purple-100 text-purple-800', 'moderator' => 'bg-blue-100 text-blue-800', 'user' => 'bg-gray-100 text-gray-800'];
                            @endphp
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $roleColors[$user->role] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($user->role) }}
                            </span>
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd>
                            @if($user->isBanned())
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Banned</span>
                                <p class="text-xs text-red-600 mt-1">Reason: {{ $user->ban_reason }}</p>
                                <p class="text-xs text-gray-500">Since: {{ $user->banned_at?->format('M d, Y H:i') }}</p>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @endif
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Joined</dt>
                        <dd class="text-sm text-gray-900">{{ $user->created_at->format('M d, Y H:i') }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Email Verified</dt>
                        <dd class="text-sm text-gray-900">{{ $user->email_verified_at ? $user->email_verified_at->format('M d, Y H:i') : 'Not verified' }}</dd>
                    </dl>
                </div>

                {{-- Activity Stats --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Activity</h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Total Bids</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ number_format($activity['total_bids']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Bids Today</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ number_format($activity['bids_today']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Auctions Created</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ number_format($activity['auctions_created']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Active Auctions</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ number_format($activity['active_auctions']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Last Activity</dt>
                            <dd class="text-sm text-gray-900">{{ $activity['last_activity'] ? \Carbon\Carbon::parse($activity['last_activity'])->diffForHumans() : 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Unique IPs</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ $activity['unique_ips'] }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Admin Actions --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Admin Actions</h3>
                <div class="flex flex-wrap gap-4 items-end">
                    {{-- Ban / Unban --}}
                    @if($user->isBanned())
                        <button onclick="unbanUser()" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-700">
                            Unban User
                        </button>
                    @elseif(!$user->isAdmin())
                        <div class="flex gap-2 items-end">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Ban Reason</label>
                                <input type="text" id="ban-reason" placeholder="Reason for ban"
                                       class="rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm">
                            </div>
                            <button onclick="banUser()" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700">
                                Ban User
                            </button>
                        </div>
                    @endif

                    {{-- Role Change --}}
                    @if(!$user->isAdmin() || auth()->id() !== $user->id)
                        <div class="flex gap-2 items-end">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Change Role</label>
                                <select id="role-select" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @foreach(['user', 'moderator', 'admin'] as $r)
                                        <option value="{{ $r }}" {{ $user->role === $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button onclick="changeRole()" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                                Update Role
                            </button>
                        </div>
                    @endif
                </div>
                <div id="action-message" class="mt-4 hidden"></div>
            </div>

            {{-- Recent Bids --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 pb-2">
                    <h3 class="text-lg font-semibold">Recent Bids</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Auction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($recentBids as $bid)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm text-gray-500">#{{ $bid->id }}</td>
                                <td class="px-6 py-3 text-sm">
                                    @if($bid->auction)
                                        <a href="{{ route('admin.auctions.show', $bid->auction_id) }}" class="text-indigo-600 hover:underline">
                                            {{ Str::limit($bid->auction->title, 35) }}
                                        </a>
                                        <span class="text-xs text-gray-400 ml-1">({{ $bid->auction->status }})</span>
                                    @else
                                        <span class="text-gray-400">Deleted</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">${{ number_format($bid->amount, 2) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 font-mono">{{ $bid->ip_address ?? '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $bid->created_at->format('M d H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No bids from this user.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Audit History --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 pb-2">
                    <h3 class="text-lg font-semibold">Audit History</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($auditHistory as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm">
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ $log->action }}</span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">
                                    @if($log->metadata)
                                        <details class="cursor-pointer">
                                            <summary class="text-indigo-600 hover:underline">View metadata</summary>
                                            <pre class="mt-1 text-xs bg-gray-50 p-2 rounded overflow-x-auto">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">No audit history for this user.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = '{{ csrf_token() }}';
        const msgEl = document.getElementById('action-message');

        function showMessage(text, success) {
            msgEl.className = success
                ? 'mt-4 p-3 rounded bg-green-100 text-green-800 text-sm'
                : 'mt-4 p-3 rounded bg-red-100 text-red-800 text-sm';
            msgEl.textContent = text;
            msgEl.classList.remove('hidden');
        }

        async function banUser() {
            const reason = document.getElementById('ban-reason')?.value;
            if (!reason) { alert('Please enter a ban reason.'); return; }
            if (!confirm('Ban this user?')) return;

            try {
                const resp = await fetch('{{ route("admin.users.ban", $user) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ reason }),
                });
                const data = await resp.json();
                showMessage(data.message, resp.ok);
                if (resp.ok) setTimeout(() => location.reload(), 1500);
            } catch { showMessage('Request failed.', false); }
        }

        async function unbanUser() {
            if (!confirm('Unban this user?')) return;

            try {
                const resp = await fetch('{{ route("admin.users.unban", $user) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({}),
                });
                const data = await resp.json();
                showMessage(data.message, resp.ok);
                if (resp.ok) setTimeout(() => location.reload(), 1500);
            } catch { showMessage('Request failed.', false); }
        }

        async function changeRole() {
            const role = document.getElementById('role-select').value;
            if (!confirm(`Change role to ${role}?`)) return;

            try {
                const resp = await fetch('{{ route("admin.users.role", $user) }}', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ role }),
                });
                const data = await resp.json();
                showMessage(data.message, resp.ok);
                if (resp.ok) setTimeout(() => location.reload(), 1500);
            } catch { showMessage('Request failed.', false); }
        }
    </script>
    @endpush
</x-app-layout>
