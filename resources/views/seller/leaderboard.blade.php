<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-700">Marketplace rankings</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-950">Seller Leaderboard</h2>
            </div>
            <form method="GET" action="{{ route('sellers.leaderboard') }}">
                <label for="period" class="sr-only">Leaderboard period</label>
                <select id="period" name="period" onchange="this.form.submit()" class="rounded-full border-slate-200 bg-white text-sm font-semibold text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @foreach($periodOptions as $value => $label)
                        <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-8 text-white shadow-2xl">
                <div class="absolute inset-0 opacity-80" style="background: radial-gradient(circle at 20% 20%, rgba(16,185,129,.34), transparent 30%), radial-gradient(circle at 85% 15%, rgba(251,191,36,.22), transparent 26%), linear-gradient(135deg, #0f172a, #064e3b);"></div>
                <div class="relative max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.32em] text-emerald-200">Top seller momentum</p>
                    <h3 class="mt-3 text-4xl font-black tracking-tight sm:text-5xl">Find trusted sellers with proven demand.</h3>
                    <p class="mt-4 text-sm leading-6 text-slate-200">Rankings are based on recent gross revenue, completed sales, bids received, active listings, and buyer ratings.</p>
                </div>
            </section>

            @if($leaders->isEmpty())
                <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                    Seller rankings will appear after the next analytics snapshot.
                </section>
            @else
                <section class="space-y-4">
                    @foreach($leaders as $leader)
                        @php($seller = $leader->user)
                        <article class="grid gap-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-[auto_1fr_auto] lg:items-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl {{ $loop->iteration <= 3 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }} text-xl font-black">
                                #{{ $loop->iteration }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-xl font-bold text-slate-950">{{ $seller?->name ?? 'Seller unavailable' }}</h3>
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Verified seller</span>
                                </div>
                                <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $seller?->seller_bio ?: 'No seller bio yet.' }}</p>
                                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-5">
                                    <div><span class="block text-xs uppercase tracking-wide text-slate-400">Revenue</span><strong class="text-slate-900">${{ number_format((float) $leader->total_revenue, 2) }}</strong></div>
                                    <div><span class="block text-xs uppercase tracking-wide text-slate-400">Sales</span><strong class="text-slate-900">{{ number_format((int) $leader->total_sales) }}</strong></div>
                                    <div><span class="block text-xs uppercase tracking-wide text-slate-400">Rating</span><strong class="text-slate-900">{{ number_format((float) $leader->avg_rating, 2) }}</strong></div>
                                    <div><span class="block text-xs uppercase tracking-wide text-slate-400">Listings</span><strong class="text-slate-900">{{ number_format((int) $leader->active_listings) }}</strong></div>
                                    <div><span class="block text-xs uppercase tracking-wide text-slate-400">Bids</span><strong class="text-slate-900">{{ number_format((int) $leader->total_bids_received) }}</strong></div>
                                </div>
                            </div>
                            @if($seller?->seller_slug)
                                <a href="{{ route('storefront.show', $seller->seller_slug) }}" class="inline-flex justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700">
                                    View Storefront
                                </a>
                            @endif
                        </article>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
