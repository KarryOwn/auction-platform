<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-3xl space-y-4">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">Blocked Users</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            People you block cannot message you and their bidding activity will no longer trigger notifications for your account.
                        </p>
                    </div>

                    @if($blockedUsers->isEmpty())
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500">
                            You have not blocked any users.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($blockedUsers as $blockedUser)
                                <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900">{{ $blockedUser->name }}</p>
                                        <p class="text-sm text-gray-500">{{ $blockedUser->email }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('users.show', $blockedUser) }}"
                                           class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                                            View Profile
                                        </a>
                                        <form method="POST" action="{{ route('users.block', $blockedUser) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50">
                                                Unblock
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl space-y-4">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">Export My Data</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Request a ZIP archive of your account data. Export links expire seven days after the file is ready.
                        </p>
                    </div>

                    @if($latestExportRequest && $latestExportRequest->status === 'ready')
                        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                            Your latest export is ready.
                            @if($latestExportRequest->expires_at)
                                Download before {{ $latestExportRequest->expires_at->format('M d, Y') }}.
                            @endif
                        </div>
                        <a href="{{ route('user.data-export.download', $latestExportRequest) }}"
                           class="inline-flex items-center justify-center min-h-11 px-4 py-2 rounded-md bg-green-700 text-sm font-semibold text-white hover:bg-green-800">
                            Download Latest Export
                        </a>
                    @else
                        <form method="POST" action="{{ route('user.data-export.request') }}">
                            @csrf
                            <x-primary-button>Request Export</x-primary-button>
                        </form>
                    @endif

                    @if($latestExportRequest)
                        <p class="text-xs text-gray-500">
                            Latest export status: {{ ucfirst($latestExportRequest->status) }}.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
