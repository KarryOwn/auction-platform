<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Seller Application Status</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @if(!$application)
                    <p>No application yet.</p>
                    <a href="{{ route('seller.apply.form') }}" class="text-indigo-600">Apply now</a>
                @else
                    <p><strong>Status:</strong> {{ ucfirst($application->status) }}</p>
                    <p><strong>Applied:</strong> {{ $application->created_at->toDayDateTimeString() }}</p>

                    @if($application->status === 'rejected')
                        <div class="p-3 bg-red-50 text-red-700 rounded">Reason: {{ $application->rejection_reason ?: $user->seller_rejected_reason }}</div>
                    @elseif($application->status === 'approved')
                        <a href="{{ route('seller.dashboard') }}" class="text-indigo-600">Go to seller dashboard</a>
                    @else
                        <div class="p-3 bg-yellow-50 text-yellow-700 rounded">Your application is pending review.</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
