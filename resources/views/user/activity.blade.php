<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Activity Log') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Your Recent Activity</h3>

                @if($logs->isEmpty())
                    <p class="text-gray-400 text-sm">No activity yet.</p>
                @else
                    <div class="relative">
                        {{-- Timeline line --}}
                        <div class="absolute top-0 bottom-0 left-5 w-0.5 bg-gray-200"></div>

                        <div class="space-y-6">
                            @foreach($logs as $log)
                                <div class="relative flex items-start gap-4 pl-12">
                                    {{-- Timeline dot --}}
                                    <div class="absolute left-3.5 top-1 w-3 h-3 rounded-full border-2 border-white shadow
                                        @switch(explode('.', $log->action)[0] ?? '')
                                            @case('bid') bg-green-500 @break
                                            @case('auction') bg-indigo-500 @break
                                            @case('wallet') bg-yellow-500 @break
                                            @case('profile') bg-blue-500 @break
                                            @case('watchlist') bg-pink-500 @break
                                            @case('auto_bid') bg-purple-500 @break
                                            @case('social_login') bg-orange-500 @break
                                            @default bg-gray-400
                                        @endswitch
                                    "></div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-medium text-gray-900 text-sm">
                                                @switch($log->action)
                                                    @case('bid.placed') Placed a bid @break
                                                    @case('auto_bid.set') Set auto-bid @break
                                                    @case('auto_bid.cancelled') Cancelled auto-bid @break
                                                    @case('watchlist.added') Added to watchlist @break
                                                    @case('watchlist.removed') Removed from watchlist @break
                                                    @case('auction.created.draft') Created auction draft @break
                                                    @case('auction.published') Published auction @break
                                                    @case('auction.updated') Updated auction @break
                                                    @case('auction.cancelled') Cancelled auction @break
                                                    @case('auction.image.uploaded') Uploaded auction image @break
                                                    @case('auction.image.deleted') Deleted auction image @break
                                                    @case('auction.images.reordered') Reordered auction images @break
                                                    @case('auction.deleted.draft') Deleted draft auction @break
                                                    @case('profile.updated') Updated profile @break
                                                    @case('wallet.top_up') Topped up wallet @break
                                                    @case('social_login') Logged in via social @break
                                                    @default {{ str_replace(['.', '_'], ' ', $log->action) }}
                                                @endswitch
                                            </span>
                                            <time class="text-xs text-gray-400 whitespace-nowrap">
                                                {{ $log->created_at->diffForHumans() }}
                                            </time>
                                        </div>

                                        @if($log->target_type && $log->target_id)
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                {{ class_basename($log->target_type) }} #{{ $log->target_id }}
                                            </div>
                                        @endif

                                        @if(!empty($log->metadata))
                                            <div class="text-xs text-gray-400 mt-1">
                                                @if(isset($log->metadata['title']))
                                                    {{ $log->metadata['title'] }}
                                                @elseif(isset($log->metadata['amount']))
                                                    Amount: ${{ number_format($log->metadata['amount'], 2) }}
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
