<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $seller->name }}'s Storefront</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded shadow-sm flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-4 items-center">
                <img src="{{ $seller->seller_avatar_path ? asset('storage/'.$seller->seller_avatar_path) : 'https://via.placeholder.com/80' }}" class="w-20 h-20 rounded-full object-cover" alt="avatar">
                <div>
                    <h3 class="font-semibold text-lg">{{ $seller->name }} <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Verified Seller</span></h3>
                    <p class="text-sm text-gray-600">{{ $seller->seller_bio ?: 'No bio yet.' }}</p>
                    <p class="text-xs text-gray-500 mt-1">Member since {{ $seller->created_at->toDateString() }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <div class="flex items-center gap-0.5">
                            @php
                                $filledStars = (int) round($averageRating);
                            @endphp
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-4 h-4 {{ $i <= $filledStars ? 'text-amber-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            @endfor
                        </div>
                        <p class="text-sm text-gray-600">
                            {{ number_format($averageRating, 1) }}
                            <span class="text-gray-400">({{ $ratingCount }} {{ \Illuminate\Support\Str::plural('rating', $ratingCount) }})</span>
                        </p>
                    </div>
                </div>
                </div>
                @auth
                    @if(auth()->id() !== $seller->id)
                        <div x-data="followSeller({
                            following: {{ $isFollowing ? 'true' : 'false' }},
                            count: {{ $followerCount }},
                            url: '{{ route('sellers.follow', $seller) }}'
                        })" class="w-full sm:w-auto">
                            <div class="flex flex-col items-start gap-2 sm:items-end">
                                <button type="button"
                                        @click="toggle()"
                                        :disabled="loading"
                                        class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition"
                                        :class="following ? 'border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' : 'bg-indigo-600 text-white hover:bg-indigo-700'">
                                    <span x-show="!loading" x-text="following ? 'Following ✓' : 'Follow Seller'"></span>
                                    <span x-show="loading" x-cloak>Updating...</span>
                                </button>
                                <p class="text-sm text-gray-500"><span x-text="count"></span> followers</p>
                            </div>
                        </div>
                    @endif
                @endauth
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded">Total listed: <strong>{{ $stats['total_listed'] }}</strong></div>
                <div class="bg-white p-4 rounded">Completed sales: <strong>{{ $stats['total_completed'] }}</strong></div>
            </div>

            <div class="bg-white p-6 rounded shadow-sm">
                <h3 class="font-semibold mb-3">Active Auctions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($activeAuctions as $auction)
                        <div class="border rounded p-3">
                            <a class="font-medium text-indigo-600" href="{{ route('auctions.show', $auction) }}">{{ $auction->title }}</a>
                            <p class="text-sm text-gray-600">${{ number_format($auction->current_price, 2) }}</p>
                        </div>
                    @endforeach
                </div>
                {{ $activeAuctions->links() }}
            </div>

            <div class="bg-white p-6 rounded shadow-sm">
                <h3 class="font-semibold mb-3">Completed Auctions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($completedAuctions as $auction)
                        <div class="border rounded p-3">
                            <p class="font-medium">{{ $auction->title }}</p>
                            <p class="text-sm text-gray-600">Sold: ${{ number_format($auction->winning_bid_amount, 2) }}</p>
                        </div>
                    @endforeach
                </div>
                {{ $completedAuctions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
    window.followSeller = ({ following, count, url }) => ({
        following,
        count,
        loading: false,
        async toggle() {
            if (this.loading) return;

            const previousFollowing = this.following;
            const previousCount = this.count;
            this.loading = true;
            this.following = !this.following;
            this.count = Math.max(0, this.count + (this.following ? 1 : -1));

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Unable to update follow state.');
                }

                this.following = Boolean(data.following);
                if (typeof data.follower_count !== 'undefined') {
                    this.count = Number(data.follower_count);
                }
                window.toast?.success(data.message || 'Follow status updated.');
            } catch (error) {
                this.following = previousFollowing;
                this.count = previousCount;
                window.toast?.error(error.message || 'Unable to update follow state.');
            } finally {
                this.loading = false;
            }
        }
    });
</script>
@endpush
