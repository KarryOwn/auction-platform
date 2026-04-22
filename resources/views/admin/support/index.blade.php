<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Support Inbox</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form method="GET" class="flex gap-2">
                <select name="status" class="rounded border-gray-300">
                    <option value="">Open + escalated</option>
                    @foreach(['open', 'ai_handled', 'escalated', 'closed'] as $value)
                        <option value="{{ $value }}" @selected($status === $value)>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                    @endforeach
                </select>
                <button class="px-3 py-2 rounded bg-slate-900 text-white text-sm">Filter</button>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Messages</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Last Message</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($conversations as $conversation)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-700">#{{ $conversation->id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    @if($conversation->user)
                                        <div class="font-medium text-gray-900">{{ $conversation->user->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $conversation->user->email }}</div>
                                    @else
                                        <span class="text-gray-500">Anonymous widget user</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                        {{ $conversation->status === 'escalated' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ $conversation->status === 'closed' ? 'bg-gray-100 text-gray-700' : '' }}
                                        {{ $conversation->status === 'ai_handled' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $conversation->status === 'open' ? 'bg-green-100 text-green-800' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $conversation->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ number_format($conversation->messages_count) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $conversation->last_message_at?->diffForHumans() ?? 'No messages yet' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('admin.support.show', $conversation) }}" class="text-indigo-600 hover:text-indigo-800">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No support conversations found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $conversations->links() }}
        </div>
    </div>
</x-app-layout>
