<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bid History') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="bidRetractionUi()">
            {{-- Filters --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('user.bids') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">All</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="won" @selected(request('status') === 'won')>Won</option>
                            <option value="lost" @selected(request('status') === 'lost')>Lost</option>
                        </select>
                    </div>
                    <div>
                        <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                        <input type="date" name="from" id="from" value="{{ request('from') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <div>
                        <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                        <input type="date" name="to" id="to" value="{{ request('to') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                        Filter
                    </button>
                    @if(request()->hasAny(['status', 'from', 'to']))
                        <a href="{{ route('user.bids') }}" class="px-4 py-2 text-gray-600 text-sm hover:underline">Clear</a>
                    @endif
                </form>
            </div>

            {{-- Bid List --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                @if($bids->isEmpty())
                    <div class="p-12 text-center">
                        <p class="text-gray-400 text-lg">No bids found.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($bids as $bid)
                            <div class="flex flex-col gap-4 p-4 transition hover:bg-gray-50 sm:flex-row sm:items-center">
                                <a href="{{ route('auctions.show', $bid->auction) }}" class="flex min-w-0 flex-1 items-center gap-4">
                                    <div class="w-16 h-16 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                                        @if($bid->auction->getCoverImageUrl('thumbnail'))
                                            <img src="{{ $bid->auction->getCoverImageUrl('thumbnail') }}" alt="" class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 truncate">{{ $bid->auction->title }}</div>
                                        <div class="text-sm text-gray-500">
                                            Bid: <span class="font-semibold">${{ number_format($bid->amount, 2) }}</span>
                                            · {{ $bid->created_at->format('M j, Y g:ia') }}
                                        </div>

                                        @if($bid->retractionRequest)
                                            @php
                                                $requestColors = [
                                                    'pending' => 'bg-amber-100 text-amber-800',
                                                    'approved' => 'bg-green-100 text-green-800',
                                                    'declined' => 'bg-gray-100 text-gray-700',
                                                ];
                                            @endphp
                                            <span class="mt-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $requestColors[$bid->retractionRequest->status] ?? 'bg-gray-100 text-gray-700' }}">
                                                Retraction {{ $bid->retractionRequest->status }}
                                            </span>
                                        @endif
                                    </div>
                                </a>
                                <div class="flex items-center gap-3 sm:flex-shrink-0">
                                    <div>
                                        @php
                                            $statusColors = [
                                                'winning' => 'bg-green-100 text-green-800',
                                                'outbid'  => 'bg-red-100 text-red-800',
                                                'won'     => 'bg-green-100 text-green-800',
                                                'lost'    => 'bg-gray-100 text-gray-800',
                                                'cancelled' => 'bg-yellow-100 text-yellow-800',
                                                'ended'   => 'bg-gray-100 text-gray-600',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$bid->bid_status] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ ucfirst($bid->bid_status) }}
                                        </span>
                                    </div>

                                    @if($bid->can_request_retraction)
                                        <button type="button"
                                                @click="open({
                                                    bidId: {{ $bid->id }},
                                                    auctionTitle: @js($bid->auction->title),
                                                    amount: '{{ number_format($bid->amount, 2) }}',
                                                    url: '{{ route('bids.retract', $bid) }}'
                                                })"
                                                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-amber-200 px-4 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-50">
                                            Request Retraction
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="p-4 border-t">
                        {{ $bids->links() }}
                    </div>
                @endif
            </div>

            <div x-show="show" x-cloak x-transition.opacity class="fixed inset-0 z-[90] bg-slate-950/50" style="display: none;" @click="close()"></div>
            <div x-show="show" x-cloak x-transition class="fixed inset-0 z-[100] flex items-center justify-center px-4" style="display: none;">
                <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
                    <div class="border-b border-gray-100 px-6 py-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Bid Retraction</p>
                        <h3 class="mt-2 text-lg font-semibold text-slate-950">Request a review for your highest bid</h3>
                        <p class="mt-1 text-sm text-slate-500">Admins review retraction requests manually. Use this only for genuine mistakes.</p>
                    </div>

                    <form class="space-y-5 px-6 py-6" @submit.prevent="submit()">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <p class="font-medium text-slate-900" x-text="auctionTitle"></p>
                            <p class="mt-1">Bid amount: <span class="font-semibold">$<span x-text="amount"></span></span></p>
                        </div>

                        <div>
                            <label for="retraction-reason" class="block text-sm font-medium text-slate-700">Reason</label>
                            <textarea id="retraction-reason"
                                      x-model="reason"
                                      rows="4"
                                      maxlength="2000"
                                      class="mt-2 w-full rounded-2xl border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                      placeholder="Explain why this bid should be retracted."
                                      required></textarea>
                            <p class="mt-2 text-xs text-rose-600" x-show="error" x-text="error"></p>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <button type="button"
                                    @click="close()"
                                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="loading"
                                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60">
                                <span x-show="!loading">Submit Request</span>
                                <span x-show="loading">Submitting...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@once
    @push('scripts')
        <script>
            function bidRetractionUi() {
                return {
                    show: false,
                    loading: false,
                    bidId: null,
                    auctionTitle: '',
                    amount: '',
                    url: '',
                    reason: '',
                    error: '',
                    open(config) {
                        this.show = true;
                        this.loading = false;
                        this.error = '';
                        this.bidId = config.bidId;
                        this.auctionTitle = config.auctionTitle;
                        this.amount = config.amount;
                        this.url = config.url;
                        this.reason = '';
                    },
                    close() {
                        if (this.loading) {
                            return;
                        }

                        this.show = false;
                    },
                    async submit() {
                        this.loading = true;
                        this.error = '';

                        try {
                            const response = await fetch(this.url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                },
                                body: JSON.stringify({ reason: this.reason }),
                            });

                            const data = await response.json();
                            if (!response.ok) {
                                throw new Error(data.error || data.message || 'Unable to submit bid retraction request.');
                            }

                            window.toast?.success(data.message || 'Bid retraction request submitted.');
                            window.location.reload();
                        } catch (error) {
                            this.error = error.message || 'Unable to submit bid retraction request.';
                        } finally {
                            this.loading = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
