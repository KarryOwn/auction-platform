<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Auction Schedule</h2>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('seller.auctions.index') }}"
                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium border border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    List View
                </a>
                <span
                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                >
                    Calendar View
                </span>
                <a
                    href="{{ route('seller.auctions.create') }}"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700"
                >
                    Create New Auction
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $todayCarbon = \Carbon\Carbon::parse($today);
        $gridStart = $todayCarbon->copy()->startOfWeek();
        $gridDays = $weeks * 7;
        $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden hidden md:block">
                <div class="grid grid-cols-7 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                    @foreach($weekdays as $label)
                        <div class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @for($i = 0; $i < $gridDays; $i++)
                        @php
                            $date = $gridStart->copy()->addDays($i);
                            $dateKey = $date->toDateString();
                            $items = $auctionsByDate->get($dateKey, collect());
                            $isToday = $dateKey === $today;
                            $isPast = $date->lt($todayCarbon);
                        @endphp

                        <div class="min-h-40 p-3 border-b border-r border-gray-100 dark:border-gray-700 {{ $isToday ? 'bg-indigo-50 dark:bg-indigo-950/30 border-indigo-200 dark:border-indigo-600' : '' }} {{ $isPast ? 'opacity-60' : '' }}">
                            <div class="text-sm font-medium {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-200' }}">
                                {{ $date->format('M j') }}
                            </div>

                            @if($items->isNotEmpty())
                                <div class="mt-2 space-y-1.5">
                                    @foreach($items as $item)
                                        @php
                                            $isStart = $item['type'] === 'start';
                                            $pillClasses = $isStart
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'
                                                : 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300';
                                            $prefix = $isStart ? 'Start' : 'End';
                                        @endphp

                                        <a
                                            href="{{ route('seller.auctions.edit', $item['auction']) }}"
                                            class="block text-xs px-2 py-1 rounded-md {{ $pillClasses }} truncate"
                                            title="{{ $prefix }}: {{ $item['auction']->title }}"
                                        >
                                            {{ $prefix }}: {{ \Illuminate\Support\Str::limit($item['auction']->title, 20) }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endfor
                </div>
            </div>

            <div class="md:hidden space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Timeline (mobile)</p>
                    <a href="{{ route('seller.auctions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700">Switch to List View</a>
                </div>

                @forelse($auctionsByDate as $dateKey => $items)
                    @php
                        $date = \Carbon\Carbon::parse($dateKey);
                        $isToday = $dateKey === $today;
                        $isPast = $date->lt($todayCarbon);
                    @endphp

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 {{ $isToday ? 'ring-1 ring-indigo-300 dark:ring-indigo-500' : '' }} {{ $isPast ? 'opacity-60' : '' }}">
                        <p class="text-sm font-semibold {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-800 dark:text-gray-100' }}">
                            {{ $date->format('l, M j') }}
                        </p>

                        <div class="mt-2 space-y-2">
                            @foreach($items as $item)
                                @php
                                    $isStart = $item['type'] === 'start';
                                    $pillClasses = $isStart
                                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'
                                        : 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300';
                                @endphp

                                <a href="{{ route('seller.auctions.edit', $item['auction']) }}" class="block rounded-md px-3 py-2 text-sm {{ $pillClasses }}">
                                    <span class="font-medium">{{ $isStart ? 'Starting' : 'Ending' }}</span>
                                    <span class="block truncate">{{ $item['auction']->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5 text-sm text-gray-500 dark:text-gray-400">
                        No auction starts or endings scheduled in this time window.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
