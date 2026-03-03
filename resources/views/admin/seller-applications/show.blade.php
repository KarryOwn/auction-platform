<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Review Seller Application</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white p-6 rounded shadow-sm space-y-3">
                <p><strong>User:</strong> {{ $application->user->name }} ({{ $application->user->email }})</p>
                <p><strong>Status:</strong> {{ ucfirst($application->status) }}</p>
                <p><strong>Reason:</strong></p>
                <p class="text-gray-700 whitespace-pre-line break-all">{{ $application->reason }}</p>
                <p><strong>Experience:</strong></p>
                <p class="text-gray-700 whitespace-pre-line break-all">{{ $application->experience ?: 'N/A' }}</p>
            </div>

            @if($application->status === 'pending')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form method="POST" action="{{ route('admin.seller-applications.approve', $application) }}" class="bg-white p-4 rounded shadow-sm">
                        @csrf
                        <button class="px-4 py-2 bg-green-600 text-white rounded">Approve</button>
                    </form>

                    <form method="POST" action="{{ route('admin.seller-applications.reject', $application) }}" class="bg-white p-4 rounded shadow-sm space-y-2">
                        @csrf
                        <textarea name="rejection_reason" rows="3" class="w-full rounded border-gray-300" placeholder="Rejection reason" required></textarea>
                        <button class="px-4 py-2 bg-red-600 text-white rounded">Reject</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
