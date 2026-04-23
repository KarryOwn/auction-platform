<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage Auctions</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Status Filter Tabs --}}
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.auctions.index') }}"
                   class="px-4 py-2 rounded-md text-sm font-medium {{ !request('status') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    All ({{ $statusCounts['all'] }})
                </a>
                @foreach(['active', 'completed', 'cancelled', 'draft'] as $s)
                    <a href="{{ route('admin.auctions.index', ['status' => $s]) }}"
                       class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === $s ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ ucfirst($s) }} ({{ $statusCounts[$s] }})
                    </a>
                @endforeach
                <a href="{{ route('admin.auctions.index', ['auth_cert' => 'uploaded']) }}"
                   class="px-4 py-2 rounded-md text-sm font-medium {{ request('auth_cert') === 'uploaded' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    Certificate Approvals ({{ $certificateApprovalCount }})
                </a>
            </div>

            {{-- Search --}}
            <div class="mb-4">
                <form method="GET" action="{{ route('admin.auctions.index') }}" class="flex gap-2">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    @if(request('auth_cert'))
                        <input type="hidden" name="auth_cert" value="{{ request('auth_cert') }}">
                    @endif
                          <input type="text" name="search" value="{{ request('search') }}"
                              placeholder="Search auctions by title or seller..."
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Search</button>
                    @if(request('search'))
                        <a href="{{ route('admin.auctions.index', ['status' => request('status')]) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300">Clear</a>
                    @endif
                </form>
            </div>

            {{-- Auctions Table --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seller</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bids</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ends At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($auctions as $auction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#{{ $auction->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('admin.auctions.show', $auction) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                        {{ Str::limit($auction->title, 40) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($auction->seller)
                                        <a href="{{ route('admin.users.show', $auction->seller->id) }}" class="text-indigo-600 hover:underline">
                                            {{ $auction->seller->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${{ number_format($auction->current_price, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $auction->bids_count ?? 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $colors = ['active' => 'bg-green-100 text-green-800', 'completed' => 'bg-blue-100 text-blue-800', 'cancelled' => 'bg-red-100 text-red-800', 'draft' => 'bg-gray-100 text-gray-800'];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colors[$auction->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($auction->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $certColors = [
                                            'none' => 'bg-gray-100 text-gray-700',
                                            'uploaded' => 'bg-amber-100 text-amber-800',
                                            'verified' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                        ];
                                        $certLabels = [
                                            'none' => 'None',
                                            'uploaded' => 'Needs review',
                                            'verified' => 'Verified',
                                            'rejected' => 'Rejected',
                                        ];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $certColors[$auction->authenticity_cert_status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $certLabels[$auction->authenticity_cert_status] ?? ucfirst((string) $auction->authenticity_cert_status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $auction->end_time->format('M d, Y H:i') }}
                                    @if($auction->status === 'active' && $auction->end_time->isFuture())
                                        <div class="text-xs text-orange-500">{{ $auction->end_time->diffForHumans() }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm" x-data="adminFeatureManager({
                                    featureUrl: '{{ route('admin.auctions.feature', $auction) }}',
                                    unfeatureUrl: '{{ route('admin.auctions.unfeature', $auction) }}',
                                    csrf: '{{ csrf_token() }}',
                                    initialFeatured: @js((bool) $auction->is_featured),
                                    initialFeaturedUntil: @js($auction->featured_until?->format('M d, Y H:i')),
                                    initialPosition: @js($auction->featured_position),
                                })">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.auctions.show', $auction) }}" class="text-indigo-600 hover:text-indigo-900">View</a>

                                        <template x-if="featured">
                                            <span class="inline-flex items-center gap-1">
                                                <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800">★ Featured</span>
                                                <button type="button" class="text-red-600 hover:text-red-800" :disabled="isSubmitting" @click="removeFeature">Remove</button>
                                            </span>
                                        </template>

                                        <template x-if="!featured">
                                            <button type="button" class="text-amber-700 hover:text-amber-900" @click="showFeatureForm = !showFeatureForm">
                                                Feature
                                            </button>
                                        </template>
                                    </div>

                                    <div x-show="featured && featuredUntil" class="mt-1 text-xs text-gray-500" x-cloak>
                                        Until <span x-text="featuredUntil"></span>
                                    </div>

                                    <div x-show="showFeatureForm && !featured" x-cloak class="mt-2 p-3 rounded-md border border-gray-200 bg-gray-50 space-y-2 min-w-56">
                                        <label class="block text-xs text-gray-600">
                                            Duration
                                            <select x-model.number="duration" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                                <option value="24">24 hours</option>
                                                <option value="48">48 hours</option>
                                                <option value="72">72 hours</option>
                                                <option value="168">1 week</option>
                                                <option value="720">30 days</option>
                                            </select>
                                        </label>
                                        <label class="block text-xs text-gray-600">
                                            Position (optional)
                                            <input x-model.number="position" type="number" min="1" max="20" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="1-20">
                                        </label>
                                        <button type="button"
                                                class="px-3 py-1.5 rounded-md bg-indigo-600 text-white text-xs hover:bg-indigo-700 disabled:opacity-60"
                                                :disabled="isSubmitting"
                                                @click="submitFeature">
                                            Confirm
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-gray-500">No auctions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $auctions->links() }}
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        if (!window.adminFeatureManager) {
            window.adminFeatureManager = function (config) {
                return {
                    featured: Boolean(config.initialFeatured),
                    featuredUntil: config.initialFeaturedUntil,
                    position: config.initialPosition ?? '',
                    duration: 24,
                    showFeatureForm: false,
                    isSubmitting: false,
                    notifySuccess(message) {
                        if (window.toast && typeof window.toast.success === 'function') {
                            window.toast.success(message);
                        }
                    },
                    notifyError(message) {
                        if (window.toast && typeof window.toast.error === 'function') {
                            window.toast.error(message);
                        }
                    },
                    async submitFeature() {
                        this.isSubmitting = true;

                        try {
                            const response = await fetch(config.featureUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    duration_hours: this.duration,
                                    position: this.position || null,
                                }),
                            });

                            const data = await response.json();

                            if (!response.ok) {
                                this.notifyError(data.message || 'Failed to feature auction.');
                                return;
                            }

                            this.featured = true;
                            this.featuredUntil = data.data?.featured_until_human || null;
                            this.position = data.data?.featured_position || null;
                            this.showFeatureForm = false;
                            this.notifySuccess(data.message || 'Auction featured.');
                        } catch (error) {
                            this.notifyError('Request failed.');
                        } finally {
                            this.isSubmitting = false;
                        }
                    },
                    async removeFeature() {
                        this.isSubmitting = true;

                        try {
                            const response = await fetch(config.unfeatureUrl, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': config.csrf,
                                    'Accept': 'application/json',
                                },
                            });

                            const data = await response.json();

                            if (!response.ok) {
                                this.notifyError(data.message || 'Failed to remove feature.');
                                return;
                            }

                            this.featured = false;
                            this.featuredUntil = null;
                            this.position = null;
                            this.showFeatureForm = false;
                            this.notifySuccess(data.message || 'Feature removed.');
                        } catch (error) {
                            this.notifyError('Request failed.');
                        } finally {
                            this.isSubmitting = false;
                        }
                    },
                };
            };
        }
    </script>
    @endpush
</x-app-layout>
