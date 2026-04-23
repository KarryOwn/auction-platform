<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $user->name }}'s Profile
            </h2>

            @auth
                @if(auth()->id() !== $user->id)
                    <div
                        x-data="blockUserProfile({
                            blocked: {{ $viewerHasBlocked ? 'true' : 'false' }},
                            url: '{{ route('users.block', $user) }}',
                            csrf: '{{ csrf_token() }}'
                        })"
                        @click.outside="open = false"
                        class="relative"
                    >
                        <button type="button"
                                @click="open = !open"
                                class="inline-flex min-h-11 items-center justify-center rounded-full border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
                            </svg>
                            <span class="sr-only">Profile actions</span>
                        </button>

                        <div x-show="open"
                             x-cloak
                             x-transition
                             class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl"
                             style="display: none;">
                            <button type="button"
                                    @click="toggle()"
                                    x-bind:disabled="loading"
                                    class="flex w-full items-center justify-between px-4 py-3 text-left text-sm transition hover:bg-gray-50"
                                    :class="blocked ? 'text-rose-700' : 'text-gray-700'">
                                <span x-show="!blocked">Block User</span>
                                <span x-show="blocked" x-cloak>Unblock User</span>
                                <span class="text-xs text-gray-400" x-text="loading ? 'Saving...' : ''"></span>
                            </button>
                            <p class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500">
                                Blocking prevents messages and suppresses notifications from this user.
                            </p>
                        </div>
                    </div>
                @endif
            @endauth
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                {{-- Profile Header --}}
                <div class="bg-gradient-to-r from-[#1f3425] via-[#355e3b] to-[#c9802f] h-32"></div>
                <div class="px-6 pb-6">
                    <div class="flex items-end -mt-12 mb-4">
                        <div class="w-24 h-24 rounded-full border-4 border-white bg-gray-200 overflow-hidden flex-shrink-0">
                            @if($user->getAvatarUrl('profile'))
                                <img src="{{ $user->getAvatarUrl('profile') }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-3xl font-bold text-gray-400">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                            @endif
                        </div>
                        <div class="ml-4 mb-1">
                            <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
                            @if($user->username)
                                <p class="text-sm text-gray-500">{{ '@' . $user->username }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Bio --}}
                    @if($user->bio)
                        <div class="mb-6">
                            <p class="text-gray-700">{{ $user->bio }}</p>
                        </div>
                    @endif

                    {{-- Stats --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 py-4 border-t border-gray-100">
                        <div>
                            <div class="text-sm font-medium text-gray-500">Member Since</div>
                            <div class="mt-1 text-gray-900 font-semibold">{{ $memberSince->format('F Y') }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">Auctions Won</div>
                            <div class="mt-1 text-gray-900 font-semibold">{{ $totalWins }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">Rating</div>
                            <div class="mt-1 text-gray-900 font-semibold">
                                {{ number_format($averageRating ?? 0, 1) }}
                                <span class="text-sm text-gray-500 font-normal">({{ $ratingCount }} {{ \Illuminate\Support\Str::plural('rating', $ratingCount) }})</span>
                            </div>
                        </div>
                    </div>

                    {{-- Seller Badge --}}
                    @if($user->isVerifiedSeller())
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    ✓ Verified Seller
                                </span>
                                @if($user->seller_slug)
                                    <a href="{{ route('storefront.show', $user->seller_slug) }}" class="text-sm text-indigo-600 hover:underline">
                                        View Storefront →
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@once
    @push('scripts')
        <script>
            function blockUserProfile(config) {
                return {
                    open: false,
                    loading: false,
                    blocked: Boolean(config.blocked),
                    async toggle() {
                        if (this.loading) {
                            return;
                        }

                        this.loading = true;

                        try {
                            const response = await fetch(config.url, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                },
                            });

                            const data = await response.json();

                            if (!response.ok) {
                                throw new Error(data.error || data.message || 'Unable to update block status.');
                            }

                            this.blocked = Boolean(data.blocked);
                            this.open = false;
                            window.toast?.success(data.message || 'Block status updated.');
                        } catch (error) {
                            window.toast?.error(error.message || 'Unable to update block status.');
                        } finally {
                            this.loading = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
