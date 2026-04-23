<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-600">Credits store</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-950">Credits & Power-Ups</h2>
            </div>
            <a href="{{ route('user.wallet') }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-amber-300 hover:text-amber-700">
                Manage wallet
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800 shadow-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-2xl sm:p-8">
                <div class="absolute inset-0 opacity-70" style="background: radial-gradient(circle at 12% 20%, rgba(251,191,36,.32), transparent 28%), radial-gradient(circle at 82% 12%, rgba(45,212,191,.24), transparent 30%), linear-gradient(135deg, rgba(15,23,42,1), rgba(41,37,36,.94));"></div>
                <div class="relative grid gap-6 lg:grid-cols-[1.1fr_.9fr] lg:items-end">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.32em] text-amber-200">Available credits</p>
                        <div class="mt-4 flex flex-wrap items-end gap-4">
                            <p class="text-5xl font-black tracking-tight sm:text-6xl">${{ number_format($availableBalance, 2) }}</p>
                            <p class="pb-2 text-sm text-slate-300">Wallet credits ready for listing boosts, feature slots, and seller growth tools.</p>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-300">Active listings</p>
                            <p class="mt-3 text-3xl font-bold">{{ $user->active_auction_count }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-300">Store status</p>
                            <p class="mt-3 text-lg font-bold">{{ $eligibleAuctions->isEmpty() ? 'No eligible auctions' : 'Ready to boost' }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 lg:grid-cols-3">
                @foreach($powerUps as $key => $powerUp)
                    <article class="flex min-h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-600">{{ $powerUp['badge'] }}</p>
                                    <h3 class="mt-2 text-xl font-bold text-slate-950">{{ $powerUp['name'] }}</h3>
                                </div>
                                <p class="rounded-2xl bg-amber-100 px-3 py-2 text-lg font-black text-amber-900">${{ number_format($powerUp['price'], 2) }}</p>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-600">{{ $powerUp['description'] }}</p>
                        </div>

                        <div class="mt-auto space-y-3 border-t border-slate-100 bg-slate-50 p-6" x-data="{ selectedAuction: '{{ $eligibleAuctions->first()?->id }}' }">
                        <form method="POST" action="{{ route('user.credits.power-ups.store') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="power_up" value="{{ $key }}">

                            <label for="auction-{{ $key }}" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Apply to auction</label>
                            <select id="auction-{{ $key }}" name="auction_id" x-model="selectedAuction" @disabled($eligibleAuctions->isEmpty()) class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                @forelse($eligibleAuctions as $auction)
                                    <option value="{{ $auction->id }}">
                                        {{ $auction->title }}
                                        @if($auction->is_currently_featured)
                                            (featured until {{ $auction->featured_until?->format('M j') }})
                                        @endif
                                    </option>
                                @empty
                                    <option>No active auctions available</option>
                                @endforelse
                            </select>

                            <button type="submit" @disabled($eligibleAuctions->isEmpty() || $availableBalance < $powerUp['price']) class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:bg-slate-300">
                                {{ $availableBalance < $powerUp['price'] ? 'Insufficient credits' : 'Buy power-up' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('user.credits.power-ups.stripe') }}">
                            @csrf
                            <input type="hidden" name="power_up" value="{{ $key }}">
                            <input type="hidden" name="auction_id" value="{{ $eligibleAuctions->first()?->id }}" :value="selectedAuction">
                            <button type="submit" @disabled($eligibleAuctions->isEmpty()) class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-800 shadow-sm transition hover:border-amber-400 hover:text-amber-700 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                                Pay with Stripe
                            </button>
                        </form>
                        <p class="text-center text-xs text-slate-500">Stripe checkout uses the selected auction from the dropdown above.</p>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="grid gap-6 lg:grid-cols-[.95fr_1.05fr]">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">How credits work</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-950">Spend wallet credits on growth moments</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Referral rewards, seller credits, and wallet top-ups share one available balance. Power-ups only use available credits, so held bid funds stay protected.</p>
                    <a href="{{ route('user.referrals') }}" class="mt-5 inline-flex rounded-full bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-200">
                        Earn referral credits
                    </a>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Recent wallet activity</p>
                            <h3 class="mt-2 text-xl font-bold text-slate-950">Credits ledger</h3>
                        </div>
                        <a href="{{ route('user.wallet') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-900">View all</a>
                    </div>
                    <div class="mt-5 divide-y divide-slate-100">
                        @forelse($recentTransactions as $transaction)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $transaction->description }}</p>
                                    <p class="text-xs text-slate-500">{{ $transaction->created_at?->diffForHumans() }} · {{ str_replace('_', ' ', $transaction->type) }}</p>
                                </div>
                                <p class="text-sm font-bold {{ $transaction->isDebit() ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ $transaction->isDebit() ? '-' : '+' }}${{ number_format((float) $transaction->amount, 2) }}
                                </p>
                            </div>
                        @empty
                            <p class="py-6 text-sm text-slate-500">No wallet activity yet. Add funds or earn referral credits to start using power-ups.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
