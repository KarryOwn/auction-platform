<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Seller Applications</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex gap-2">
                <select name="status" class="rounded border-gray-300">
                    <option value="">All</option>
                    @foreach(['pending','approved','rejected'] as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button class="px-3 py-2 bg-gray-800 text-white rounded">Filter</button>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead><tr><th class="px-4 py-2 text-left">User</th><th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2 text-left">Submitted</th><th></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($applications as $application)
                            <tr>
                                <td class="px-4 py-2">{{ $application->user->name }} ({{ $application->user->email }})</td>
                                <td class="px-4 py-2">{{ ucfirst($application->status) }}</td>
                                <td class="px-4 py-2">{{ $application->created_at->diffForHumans() }}</td>
                                <td class="px-4 py-2"><a href="{{ route('admin.seller-applications.show', $application) }}" class="text-indigo-600">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $applications->links() }}
        </div>
    </div>
</x-app-layout>
