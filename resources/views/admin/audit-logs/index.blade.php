<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Logs</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Filters --}}
            <div class="mb-4">
                <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="flex flex-wrap gap-2">
                    <select name="action" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Actions</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                        @endforeach
                    </select>
                    <select name="target_type" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Target Types</option>
                        @foreach(['user', 'auction', 'report'] as $type)
                            <option value="{{ $type }}" {{ request('target_type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="user_id" value="{{ request('user_id') }}"
                           placeholder="Admin User ID"
                           class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Filter</button>
                    @if(request()->hasAny(['action', 'target_type', 'user_id']))
                        <a href="{{ route('admin.audit-logs.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300">Clear</a>
                    @endif
                </form>
            </div>

            {{-- Logs Table --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metadata</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{{ $log->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $actionColors = [
                                            'user.banned'            => 'bg-red-100 text-red-800',
                                            'user.unbanned'          => 'bg-green-100 text-green-800',
                                            'user.role_changed'      => 'bg-purple-100 text-purple-800',
                                            'auction.force_cancelled'=> 'bg-red-100 text-red-800',
                                            'auction.extended'       => 'bg-blue-100 text-blue-800',
                                            'report.reviewed'        => 'bg-yellow-100 text-yellow-800',
                                        ];
                                        $color = $actionColors[$log->action] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="px-2 py-1 rounded text-xs font-medium {{ $color }}">{{ $log->action }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($log->user)
                                        <a href="{{ route('admin.users.show', $log->user->id) }}" class="text-indigo-600 hover:underline">
                                            {{ $log->user->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">System</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($log->target_type === 'user')
                                        <a href="{{ route('admin.users.show', $log->target_id) }}" class="text-indigo-600 hover:underline">
                                            User #{{ $log->target_id }}
                                        </a>
                                    @elseif($log->target_type === 'auction')
                                        <a href="{{ route('admin.auctions.show', $log->target_id) }}" class="text-indigo-600 hover:underline">
                                            Auction #{{ $log->target_id }}
                                        </a>
                                    @else
                                        {{ ucfirst($log->target_type) }} #{{ $log->target_id }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">{{ $log->ip_address ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($log->metadata)
                                        <details class="cursor-pointer">
                                            <summary class="text-indigo-600 hover:underline">View</summary>
                                            <pre class="mt-1 text-xs bg-gray-50 p-2 rounded overflow-x-auto max-w-md">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">No audit logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $logs->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
