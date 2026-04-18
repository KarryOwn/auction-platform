<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-teal-600">Wallet Center</p>
                <h2 class="mt-1 font-serif text-3xl leading-tight text-slate-900">
                    {{ __('My Wallet') }}
                </h2>
            </div>
            <p class="text-sm text-slate-500">Track funds, manage payouts, and review every balance movement.</p>
        </div>
    </x-slot>

    <style>
        .wallet-grid-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(15, 23, 42, 0.09) 1px, transparent 0);
            background-size: 24px 24px;
        }

        .wallet-reveal {
            opacity: 0;
            transform: translateY(20px);
        }

        .wallet-reveal.is-visible {
            animation: wallet-fade-up 640ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .wallet-reveal[data-delay="1"].is-visible {
            animation-delay: 80ms;
        }

        .wallet-reveal[data-delay="2"].is-visible {
            animation-delay: 160ms;
        }

        .wallet-reveal[data-delay="3"].is-visible {
            animation-delay: 240ms;
        }

        @keyframes wallet-fade-up {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <div class="relative isolate overflow-hidden py-10 sm:py-12">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
            <div class="absolute -top-24 left-1/3 h-72 w-72 rounded-full bg-teal-300/40 blur-3xl"></div>
            <div class="absolute top-1/2 -right-24 h-80 w-80 -translate-y-1/2 rounded-full bg-amber-200/40 blur-3xl"></div>
            <div class="wallet-grid-pattern absolute inset-0 opacity-50"></div>
        </div>

        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div data-wallet-reveal class="wallet-reveal rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div data-wallet-reveal class="wallet-reveal rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div data-wallet-reveal class="wallet-reveal rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 shadow-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <section data-wallet-reveal data-delay="1" class="wallet-reveal grid gap-4 sm:grid-cols-3">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-slate-500">Total Balance</p>
                    </div>
                    <p class="mt-4 text-3xl font-bold text-slate-900">${{ number_format($user->wallet_balance, 2) }}</p>
                </article>
                <article class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-200 text-emerald-700">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-emerald-800">Available to Bid</p>
                    </div>
                    <p class="mt-4 text-3xl font-bold text-emerald-700">${{ number_format($user->availableBalance(), 2) }}</p>
                </article>
                <article class="rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-200 text-amber-700">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-amber-800">Held in Escrow</p>
                    </div>
                    <p class="mt-4 text-3xl font-bold text-amber-700">${{ number_format((float) $user->held_balance, 2) }}</p>
                </article>
            </section>

            <section data-wallet-reveal data-delay="2" class="wallet-reveal grid gap-6 xl:grid-cols-2">
                <article class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm backdrop-blur-sm sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-serif text-2xl text-slate-900">Add Funds</h3>
                            <p class="mt-2 text-sm text-slate-500">Use Stripe Checkout for a secure top-up.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-emerald-700">Instant</span>
                    </div>

                    <form method="POST" action="{{ route('user.wallet.stripe.checkout') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label for="amount" class="block text-sm font-medium text-slate-700">Top-Up Amount (USD)</label>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach([50, 100, 250, 500] as $preset)
                                    <button
                                        type="button"
                                        onclick="document.getElementById('amount').value = {{ $preset }}"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-teal-300 hover:bg-teal-50 hover:text-teal-700"
                                    >
                                        +${{ $preset }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <input
                                type="number"
                                name="amount"
                                id="amount"
                                min="1"
                                max="50000"
                                step="1"
                                value="{{ old('amount', 50) }}"
                                placeholder="Custom amount"
                                class="block w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-teal-500 focus:ring-teal-500 py-3"
                            >
                            @error('amount')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                            </svg>
                            Pay with Stripe
                        </button>
                    </form>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm backdrop-blur-sm sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-serif text-2xl text-slate-900">Withdraw to Bank</h3>
                            <p class="mt-2 text-sm text-slate-500">Payouts are managed by Stripe Connect.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-amber-700">Secure</span>
                    </div>

                    @if(! $user->hasConnectedBank())
                        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-semibold text-amber-900">Connect a bank account before your first withdrawal.</p>
                            <p class="mt-1 text-xs text-amber-700">Setup usually takes around 2 minutes.</p>
                            <a
                                href="{{ route('wallet.connect.onboard') }}"
                                class="mt-4 inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700"
                            >
                                Connect Bank
                            </a>
                        </div>
                    @else
                        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium">Bank account connected.</span>
                                <a href="{{ route('wallet.connect.dashboard') }}" class="font-semibold text-emerald-700 underline hover:text-emerald-900">Manage in Stripe</a>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('user.wallet.withdraw') }}" class="mt-6 space-y-4">
                            @csrf
                            <div>
                                <label for="withdraw_amount" class="block text-sm font-medium text-slate-700">
                                    Withdraw Amount
                                    <span class="text-slate-500">(available: ${{ number_format($user->availableBalance(), 2) }})</span>
                                </label>
                                <input
                                    type="number"
                                    name="amount"
                                    id="withdraw_amount"
                                    min="1"
                                    max="{{ $user->availableBalance() }}"
                                    step="1"
                                    value="{{ old('amount', min(100, $user->availableBalance())) }}"
                                    placeholder="Amount to withdraw"
                                    class="mt-2 block w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-teal-500 focus:ring-teal-500 py-3"
                                >
                                @error('amount')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-teal-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-600"
                            >
                                Withdraw to Bank
                            </button>
                        </form>
                    @endif
                </article>
            </section>

            @if($activeHolds->isNotEmpty())
                <section data-wallet-reveal data-delay="2" class="wallet-reveal rounded-3xl border border-amber-200 bg-white/90 p-6 shadow-sm backdrop-blur-sm sm:p-7">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="font-serif text-2xl text-slate-900">Active Escrow Holds</h3>
                            <p class="text-sm text-slate-500">Funds currently reserved for live bids.</p>
                        </div>
                        <span class="text-sm font-semibold text-amber-700">${{ number_format($user->held_balance, 2) }} total</span>
                    </div>

                    <div class="mt-5 grid gap-3">
                        @foreach($activeHolds as $hold)
                            <article class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 sm:px-5">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm font-semibold text-slate-900">{{ $hold->auction->title ?? "Auction #{$hold->auction_id}" }}</p>
                                    <p class="text-sm font-semibold text-amber-700">${{ number_format($hold->amount, 2) }} held</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section data-wallet-reveal data-delay="3" class="wallet-reveal overflow-hidden rounded-3xl border border-slate-200 bg-white/90 shadow-sm backdrop-blur-sm">
                <div class="border-b border-slate-200 p-6 sm:p-7">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="font-serif text-2xl text-slate-900">Transaction History</h3>
                            <p class="text-sm text-slate-500">Filter by type or date range, then export for accounting.</p>
                        </div>
                        <a
                            href="{{ route('user.wallet.export') }}"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-100"
                        >
                            Export CSV
                        </a>
                    </div>

                    <form method="GET" action="{{ route('user.wallet') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <div class="xl:col-span-2">
                            <label for="type" class="block text-sm font-medium text-slate-700">Type</label>
                            <select id="type" name="type" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="">All transaction types</option>
                                <option value="deposit" @selected(request('type') === 'deposit')>Deposits</option>
                                <option value="payment" @selected(request('type') === 'payment')>Payments</option>
                                <option value="bid_hold" @selected(request('type') === 'bid_hold')>Bid Holds</option>
                                <option value="bid_release" @selected(request('type') === 'bid_release')>Bid Releases</option>
                                <option value="withdrawal" @selected(request('type') === 'withdrawal')>Withdrawals</option>
                                <option value="refund" @selected(request('type') === 'refund')>Refunds</option>
                                <option value="seller_credit" @selected(request('type') === 'seller_credit')>Seller Credits</option>
                            </select>
                        </div>
                        <div>
                            <label for="from" class="block text-sm font-medium text-slate-700">From</label>
                            <input
                                type="date"
                                id="from"
                                name="from"
                                value="{{ request('from') }}"
                                class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                            >
                        </div>
                        <div>
                            <label for="to" class="block text-sm font-medium text-slate-700">To</label>
                            <input
                                type="date"
                                id="to"
                                name="to"
                                value="{{ request('to') }}"
                                class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                            >
                        </div>
                        <div class="flex items-end gap-2 xl:justify-end">
                            <button
                                type="submit"
                                class="inline-flex flex-1 items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 xl:flex-none"
                            >
                                Apply
                            </button>
                            <a
                                href="{{ route('user.wallet') }}"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-100"
                            >
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                @if($transactions->isEmpty())
                    <div class="p-12 text-center">
                        <p class="font-serif text-2xl text-slate-800">No transactions yet.</p>
                        <p class="mt-2 text-sm text-slate-500">Your wallet activity will appear here once funds move.</p>
                    </div>
                @else
                    <div class="space-y-3 p-4 md:hidden">
                        @foreach($transactions as $tx)
                            @php
                                $badgeClass = match($tx->type) {
                                    'deposit', 'refund', 'seller_credit' => 'bg-emerald-100 text-emerald-700',
                                    'bid_release' => 'bg-cyan-100 text-cyan-700',
                                    'bid_hold' => 'bg-amber-100 text-amber-700',
                                    'payment', 'withdrawal' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">
                                        {{ ucfirst(str_replace('_', ' ', $tx->type)) }}
                                    </span>
                                    <span class="text-xs text-slate-500">{{ $tx->created_at->format('M j, Y g:ia') }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-700">{{ $tx->description ?? '-' }}</p>
                                <div class="mt-3 flex items-end justify-between">
                                    <p class="text-xs text-slate-500">Balance: ${{ number_format((float) $tx->balance_after, 2) }}</p>
                                    <p class="text-base font-semibold {{ $tx->isCredit() ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $tx->isCredit() ? '+' : '-' }}${{ number_format(abs((float) $tx->amount), 2) }}
                                    </p>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($transactions as $tx)
                                    @php
                                        $badgeClass = match($tx->type) {
                                            'deposit', 'refund', 'seller_credit' => 'bg-emerald-100 text-emerald-700',
                                            'bid_release' => 'bg-cyan-100 text-cyan-700',
                                            'bid_hold' => 'bg-amber-100 text-amber-700',
                                            'payment', 'withdrawal' => 'bg-rose-100 text-rose-700',
                                            default => 'bg-slate-100 text-slate-700',
                                        };
                                    @endphp
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $tx->created_at->format('M j, Y g:ia') }}</td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">
                                                {{ ucfirst(str_replace('_', ' ', $tx->type)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-700">{{ $tx->description ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold {{ $tx->isCredit() ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $tx->isCredit() ? '+' : '-' }}${{ number_format(abs((float) $tx->amount), 2) }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-slate-600">
                                            ${{ number_format((float) $tx->balance_after, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-slate-200 p-4">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const blocks = document.querySelectorAll('[data-wallet-reveal]');

                if (!blocks.length) {
                    return;
                }

                if (!('IntersectionObserver' in window)) {
                    blocks.forEach((block) => block.classList.add('is-visible'));
                    return;
                }

                const observer = new IntersectionObserver((entries, instance) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add('is-visible');
                        instance.unobserve(entry.target);
                    });
                }, {
                    threshold: 0.16,
                });

                blocks.forEach((block) => observer.observe(block));
            });
        </script>
    @endpush
</x-app-layout>
