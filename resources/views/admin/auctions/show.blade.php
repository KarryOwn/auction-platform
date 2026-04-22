<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Auction #{{ $auction->id }}: {{ Str::limit($auction->title, 50) }}
            </h2>
            <a href="{{ route('admin.auctions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Auctions</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Suspicious Activity Alerts --}}
            @if(count($suspiciousActivity) > 0)
                <div class="space-y-2">
                    @foreach($suspiciousActivity as $flag)
                        @php
                            $alertColors = [
                                'critical' => 'bg-red-100 border-red-400 text-red-800',
                                'high'     => 'bg-orange-100 border-orange-400 text-orange-800',
                                'warning'  => 'bg-yellow-100 border-yellow-400 text-yellow-800',
                            ];
                            $color = $alertColors[$flag['severity']] ?? 'bg-gray-100 border-gray-400 text-gray-800';
                        @endphp
                        <div class="border-l-4 p-4 rounded {{ $color }}">
                            <div class="flex items-center">
                                <span class="font-bold uppercase text-xs mr-2">{{ $flag['severity'] }}</span>
                                <span class="text-sm">{{ $flag['detail'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Auction Details + Bid Stats --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Auction Info --}}
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Auction Details</h3>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <dt class="text-sm font-medium text-gray-500">Title</dt>
                        <dd class="text-sm text-gray-900">{{ $auction->title }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Seller</dt>
                        <dd class="text-sm text-gray-900">
                            @if($auction->seller)
                                <a href="{{ route('admin.users.show', $auction->seller->id) }}" class="text-indigo-600 hover:underline">
                                    {{ $auction->seller->name }} ({{ $auction->seller->email }})
                                </a>
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="text-sm">
                            @php
                                $colors = ['active' => 'bg-green-100 text-green-800', 'completed' => 'bg-blue-100 text-blue-800', 'cancelled' => 'bg-red-100 text-red-800', 'draft' => 'bg-gray-100 text-gray-800'];
                            @endphp
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colors[$auction->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($auction->status) }}
                            </span>
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Feature Status</dt>
                        <dd class="text-sm" x-data="adminFeatureManager({
                            featureUrl: '{{ route('admin.auctions.feature', $auction) }}',
                            unfeatureUrl: '{{ route('admin.auctions.unfeature', $auction) }}',
                            csrf: '{{ csrf_token() }}',
                            initialFeatured: @js((bool) $auction->is_featured),
                            initialFeaturedUntil: @js($auction->featured_until?->format('M d, Y H:i')),
                            initialPosition: @js($auction->featured_position),
                        })">
                            <template x-if="featured">
                                <div class="space-y-2">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800">★ Featured</span>
                                        <span class="text-xs text-gray-500" x-text="featuredUntil ? `until ${featuredUntil}` : ''"></span>
                                    </div>
                                    <div>
                                        <button type="button" class="inline-flex items-center justify-center min-h-11 p-2 px-3 rounded-md bg-red-50 text-red-700 text-xs hover:bg-red-100" :disabled="isSubmitting" @click="removeFeature">
                                            Remove feature
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="!featured">
                                <div class="space-y-2">
                                    <button type="button" class="inline-flex items-center justify-center min-h-11 p-2 px-3 rounded-md bg-amber-50 text-amber-800 text-xs hover:bg-amber-100" @click="showFeatureForm = !showFeatureForm">
                                        Feature this auction
                                    </button>

                                    <div x-show="showFeatureForm" x-cloak class="p-3 rounded-md border border-gray-200 bg-gray-50 space-y-2 max-w-xs">
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
                                                class="inline-flex items-center justify-center min-h-11 p-2 px-3 rounded-md bg-indigo-600 text-white text-xs hover:bg-indigo-700 disabled:opacity-60"
                                                :disabled="isSubmitting"
                                                @click="submitFeature">
                                            Confirm
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Starting Price</dt>
                        <dd class="text-sm text-gray-900">${{ number_format($auction->starting_price, 2) }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Current Price</dt>
                        <dd class="text-sm font-bold text-gray-900">${{ number_format($auction->current_price, 2) }}</dd>

                        @if($bidStats['redis_price'])
                            <dt class="text-sm font-medium text-gray-500">Redis Price</dt>
                            <dd class="text-sm text-gray-900">${{ number_format((float)$bidStats['redis_price'], 2) }}</dd>
                        @endif

                        <dt class="text-sm font-medium text-gray-500">Start Time</dt>
                        <dd class="text-sm text-gray-900">{{ $auction->start_time->format('M d, Y H:i:s') }}</dd>

                        <dt class="text-sm font-medium text-gray-500">End Time</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $auction->end_time->format('M d, Y H:i:s') }}
                            @if($auction->status === 'active' && $auction->end_time->isFuture())
                                <span class="text-orange-500 text-xs ml-1">({{ $auction->end_time->diffForHumans() }})</span>
                            @endif
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                        <dd class="text-sm text-gray-900 col-span-2">{{ $auction->description ?? 'No description' }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Authenticity Certificate</dt>
                        <dd class="text-sm text-gray-900 col-span-2"
                            x-data="adminAuthCertReview({
                                verifyUrl: '{{ route('admin.auctions.auth-cert.verify', $auction) }}',
                                csrf: '{{ csrf_token() }}',
                                initialStatus: @js($auction->authenticity_cert_status),
                                initialNotes: @js($auction->authenticity_cert_notes),
                            })">
                            <div class="space-y-3 max-w-xl">
                                <div class="flex flex-wrap items-center gap-2">
                                    @php
                                        $certColors = [
                                            'none' => 'bg-gray-100 text-gray-700',
                                            'uploaded' => 'bg-amber-100 text-amber-800',
                                            'verified' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $certColors[$auction->authenticity_cert_status] ?? 'bg-gray-100 text-gray-700' }}"
                                          x-text="statusLabel"></span>

                                    @if($auction->getFirstMedia('authenticity_cert'))
                                        <a href="{{ route('auctions.auth-cert.download', $auction) }}" class="text-sm text-indigo-600 hover:underline">
                                            View certificate
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-500">No file uploaded</span>
                                    @endif
                                </div>

                                @if($auction->getFirstMedia('authenticity_cert'))
                                    <div class="rounded-md border border-gray-200 bg-gray-50 p-3 space-y-3">
                                        <label class="block text-sm text-gray-700">
                                            Review notes
                                            <textarea x-model="notes" rows="3" maxlength="500" class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
                                        </label>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="inline-flex items-center justify-center min-h-11 px-3 py-2 rounded-md bg-green-600 text-white text-sm hover:bg-green-700 disabled:opacity-60" :disabled="submitting" @click="submit('verified')">
                                                Verify Certificate
                                            </button>
                                            <button type="button" class="inline-flex items-center justify-center min-h-11 px-3 py-2 rounded-md bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-60" :disabled="submitting" @click="submit('rejected')">
                                                Reject Certificate
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-500" x-show="message" x-text="message"></p>
                                    </div>
                                @endif
                            </div>
                        </dd>
                    </dl>
                </div>

                {{-- Bid Statistics --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Bid Statistics</h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Total Bids</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ number_format($bidStats['total_bids']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Unique Bidders</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ number_format($bidStats['unique_bidders']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Highest Bid</dt>
                            <dd class="text-sm font-semibold text-green-600">${{ number_format($bidStats['highest_bid'] ?? 0, 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Lowest Bid</dt>
                            <dd class="text-sm font-semibold text-gray-900">${{ number_format($bidStats['lowest_bid'] ?? 0, 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Average Bid</dt>
                            <dd class="text-sm font-semibold text-gray-900">${{ number_format($bidStats['avg_bid'] ?? 0, 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Last Bid At</dt>
                            <dd class="text-sm text-gray-900">{{ $bidStats['last_bid_at'] ? \Carbon\Carbon::parse($bidStats['last_bid_at'])->format('M d, H:i:s') : 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Admin Actions --}}
            @if($auction->status === 'active')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Admin Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Force Cancel --}}
                        <div>
                            <h4 class="text-sm font-medium text-red-600 mb-2">Force Cancel Auction</h4>
                            <form id="force-cancel-form" class="space-y-2">
                                <textarea name="reason" rows="2" placeholder="Reason for cancellation (required)"
                                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
                                          required></textarea>
                                <button type="submit" class="inline-flex items-center justify-center min-h-11 p-2 px-4 bg-red-600 text-white rounded-md text-sm hover:bg-red-700">
                                    Force Cancel
                                </button>
                            </form>
                        </div>

                        {{-- Extend Time --}}
                        <div>
                            <h4 class="text-sm font-medium text-blue-600 mb-2">Extend Auction Time</h4>
                            <form id="extend-form" class="space-y-2">
                                <input type="number" name="minutes" min="1" max="1440" placeholder="Minutes to extend"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                       required>
                                <textarea name="reason" rows="2" placeholder="Reason for extension (required)"
                                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                          required></textarea>
                                <button type="submit" class="inline-flex items-center justify-center min-h-11 p-2 px-4 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">
                                    Extend Time
                                </button>
                            </form>
                        </div>
                    </div>
                    <div id="action-message" class="mt-4 hidden"></div>
                </div>
            @endif

            {{-- Recent Bids --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 pb-2">
                    <h3 class="text-lg font-semibold">Recent Bids ({{ $recentBids->count() }})</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Agent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($recentBids as $bid)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm text-gray-500">#{{ $bid->id }}</td>
                                <td class="px-6 py-3 text-sm">
                                    @if($bid->user)
                                        <a href="{{ route('admin.users.show', $bid->user->id) }}" class="text-indigo-600 hover:underline">
                                            {{ $bid->user->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">Deleted</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">${{ number_format($bid->amount, 2) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 font-mono">{{ $bid->ip_address ?? '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 truncate max-w-xs" title="{{ $bid->user_agent }}">
                                    {{ Str::limit($bid->user_agent ?? '-', 30) }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $bid->created_at->format('M d H:i:s.u') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">No bids yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        if (!window.adminAuthCertReview) {
            window.adminAuthCertReview = function (config) {
                return {
                    status: config.initialStatus || 'none',
                    notes: config.initialNotes || '',
                    submitting: false,
                    message: '',
                    get statusLabel() {
                        return {
                            none: 'No certificate',
                            uploaded: 'Pending verification',
                            verified: 'Verified',
                            rejected: 'Rejected',
                        }[this.status] || this.status;
                    },
                    async submit(status) {
                        this.submitting = true;
                        this.message = '';

                        try {
                            const response = await fetch(config.verifyUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    status,
                                    notes: this.notes || null,
                                }),
                            });

                            const data = await response.json();

                            if (!response.ok) {
                                this.message = data.message || 'Unable to update certificate status.';
                                return;
                            }

                            this.status = data.status;
                            this.message = data.message || 'Certificate updated.';
                        } catch (error) {
                            this.message = 'Request failed.';
                        } finally {
                            this.submitting = false;
                        }
                    },
                };
            }
        }

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

        // Force Cancel
        document.getElementById('force-cancel-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to force-cancel this auction? This cannot be undone.')) return;

            const reason = this.querySelector('[name="reason"]').value;

            try {
                const resp = await fetch('{{ route("admin.auctions.force-cancel", $auction) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ reason }),
                });
                const data = await resp.json();
                if (resp.ok) {
                    window.toast.success(data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    window.toast.error(data.message || 'Error cancelling auction.');
                }
            } catch (err) {
                window.toast.error('Request failed.');
            }
        });

        // Extend Time
        document.getElementById('extend-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const minutes = this.querySelector('[name="minutes"]').value;
            const reason = this.querySelector('[name="reason"]').value;

            try {
                const resp = await fetch('{{ route("admin.auctions.extend", $auction) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ minutes: parseInt(minutes), reason }),
                });
                const data = await resp.json();
                if (resp.ok) {
                    window.toast.success(data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    window.toast.error(data.message || 'Error extending auction.');
                }
            } catch (err) {
                window.toast.error('Request failed.');
            }
        });
    </script>
    @endpush
</x-app-layout>
