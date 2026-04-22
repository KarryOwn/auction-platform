<x-guest-layout>
    <div class="mx-auto max-w-xl px-6 py-12">
        <div class="rounded-3xl border border-gray-200 bg-white p-8 shadow-sm">
            <div class="mb-6">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-600">Account Reactivation</p>
                <h1 class="mt-2 text-3xl font-bold text-gray-900">Welcome back</h1>
                <p class="mt-3 text-sm text-gray-600">
                    Your account is currently deactivated. Reactivate it to restore bidding, messaging, and dashboard access.
                </p>
            </div>

            @if(session('status'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('account.reactivate.store') }}" class="space-y-4">
                @csrf
                <button type="submit"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700">
                    Reactivate My Account
                </button>
            </form>

            <p class="mt-4 text-xs text-gray-500">
                Need a different account instead? <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-700">Return to sign in</a>.
            </p>
        </div>
    </div>
</x-guest-layout>
