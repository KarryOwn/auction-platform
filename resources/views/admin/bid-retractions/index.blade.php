<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bid Retractions</h2>
            <form method="GET" action="{{ route('admin.bid-retractions.index') }}" class="flex items-center gap-3">
                <label for="status" class="text-sm text-gray-600">Status</label>
                <select id="status" name="status" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="" @selected(!request('status'))>Pending</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="declined" @selected(request('status') === 'declined')>Declined</option>
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="adminBidRetractions()">
            <div class="rounded-3xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                @if($requests->isEmpty())
                    <div class="px-6 py-12 text-center text-sm text-gray-500">
                        No bid retraction requests found for this status.
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($requests as $requestItem)
                            @php
                                $statusColors = [
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'declined' => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <section class="px-6 py-5">
                                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0 flex-1 space-y-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-semibold text-slate-950">{{ $requestItem->auction?->title ?? 'Auction removed' }}</h3>
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColors[$requestItem->status] ?? 'bg-gray-100 text-gray-700' }}">
                                                {{ ucfirst($requestItem->status) }}
                                            </span>
                                        </div>

                                        <div class="grid gap-3 text-sm text-slate-600 sm:grid-cols-3">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bidder</p>
                                                <p class="mt-1 font-medium text-slate-900">{{ $requestItem->user?->name ?? 'Unknown user' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bid Amount</p>
                                                <p class="mt-1 font-medium text-slate-900">${{ number_format((float) ($requestItem->bid?->amount ?? 0), 2) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Requested</p>
                                                <p class="mt-1 font-medium text-slate-900">{{ $requestItem->created_at?->format('M j, Y g:ia') }}</p>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Reason</p>
                                            <p class="mt-2 text-sm text-slate-700">{{ $requestItem->reason }}</p>
                                        </div>

                                        @if($requestItem->reviewer_notes)
                                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Reviewer Notes</p>
                                                <p class="mt-2 text-sm text-slate-700">{{ $requestItem->reviewer_notes }}</p>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="w-full lg:max-w-sm">
                                        @if($requestItem->status === 'pending')
                                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                                                <label class="block text-sm font-medium text-slate-700">
                                                    Internal notes
                                                    <textarea rows="4"
                                                              x-model="notes[{{ $requestItem->id }}]"
                                                              class="mt-2 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                              placeholder="Explain why the request is approved or declined."></textarea>
                                                </label>
                                                <div class="flex flex-wrap gap-3">
                                                    <button type="button"
                                                            @click="approve({{ $requestItem->id }}, '{{ route('admin.bid-retractions.approve', $requestItem) }}')"
                                                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700">
                                                        Approve
                                                    </button>
                                                    <button type="button"
                                                            @click="decline({{ $requestItem->id }}, '{{ route('admin.bid-retractions.decline', $requestItem) }}')"
                                                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                                        Decline
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                                <p class="font-medium text-slate-900">
                                                    Reviewed by {{ $requestItem->reviewer?->name ?? 'Unknown staff member' }}
                                                </p>
                                                @if($requestItem->reviewed_at)
                                                    <p class="mt-1">Reviewed {{ $requestItem->reviewed_at->format('M j, Y g:ia') }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-100 px-6 py-4">
                        {{ $requests->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

@once
    @push('scripts')
        <script>
            function adminBidRetractions() {
                return {
                    notes: {},
                    async submit(url, note, successMessage) {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({ notes: note || '' }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.error || data.message || 'Unable to process request.');
                        }

                        window.toast?.success(data.message || successMessage);
                        window.location.reload();
                    },
                    async approve(id, url) {
                        try {
                            await this.submit(url, this.notes[id], 'Bid retraction approved.');
                        } catch (error) {
                            window.toast?.error(error.message || 'Unable to approve retraction request.');
                        }
                    },
                    async decline(id, url) {
                        try {
                            await this.submit(url, this.notes[id], 'Bid retraction declined.');
                        } catch (error) {
                            window.toast?.error(error.message || 'Unable to decline retraction request.');
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
