<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Referrals') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="theme-card overflow-hidden p-6 sm:p-8">
                <div class="grid gap-6 lg:grid-cols-[1.1fr_.9fr] lg:items-end">
                    <div>
                        <p class="theme-eyebrow">Referral dashboard</p>
                        <h3 class="mt-2 text-3xl font-black tracking-tight text-gray-900">Invite friends. Track every reward.</h3>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Share your referral link with buyers and sellers. Credited rewards land directly in your wallet.</p>
                    </div>
                    <div class="rounded-2xl border border-[var(--color-border)] bg-brand-soft p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-gray-500">Next milestone</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $nextMilestone === 0 ? 'Milestone reached' : "{$nextMilestone} credited referrals to go" }}</p>
                        <div class="mt-4 h-2 rounded-full bg-white/80">
                            <div class="h-2 rounded-full bg-brand" style="width: {{ min(100, ($creditedCount / 5) * 100) }}%"></div>
                        </div>
                    </div>
                </div>

                <div x-data="{ copied: false }" class="mt-6 grid gap-3 lg:grid-cols-[1fr_auto]">
                    <input type="text" readonly value="{{ $referralLink }}" class="w-full rounded-2xl border-gray-200 bg-white text-gray-900 shadow-sm focus:border-brand focus:ring-brand">
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $referralLink }}').then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                            class="theme-button theme-button-primary inline-flex min-h-11 items-center justify-center">
                        <span x-show="!copied">Copy Link</span>
                        <span x-show="copied" x-cloak>Copied</span>
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="mailto:?subject={{ rawurlencode('Join me on BidFlow') }}&body={{ rawurlencode($referralLink) }}" class="theme-button theme-button-secondary text-sm">Share by Email</a>
                    <a href="https://twitter.com/intent/tweet?text={{ rawurlencode('Join me on BidFlow auctions: ' . $referralLink) }}" target="_blank" rel="noopener" class="theme-button theme-button-secondary text-sm">Share on X</a>
                    <a href="{{ route('user.credits.index') }}" class="theme-button theme-button-secondary text-sm">Spend credits</a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase">Total Earned</h4>
                    <p class="mt-2 text-3xl font-bold text-green-600">${{ number_format($totalEarned, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase">Pending Rewards</h4>
                    <p class="mt-2 text-3xl font-bold text-yellow-600">${{ number_format($pendingEarned, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase">This Month</h4>
                    <p class="mt-2 text-3xl font-bold text-indigo-600">${{ number_format($earnedThisMonth, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase">Credited</h4>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $creditedCount }} / {{ $referralCount }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase">Conversion</h4>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $conversionRate }}%</p>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Your Referrals</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined On</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reward</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($referrals as $referee)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $referee->name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $referee->created_at->format('M j, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($referee->referralReward?->status === 'credited')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Credited
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        ${{ number_format($referee->referralReward?->referrer_reward ?? 0, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500 text-sm">
                                        You haven't referred anyone yet. Share your link above to get started!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($referrals->hasPages())
                    <div class="px-6 py-4 border-t">
                        {{ $referrals->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
