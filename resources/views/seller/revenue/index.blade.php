<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Revenue Reports</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex gap-2 items-end">
                <div><label class="block text-xs">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded border-gray-300"></div>
                <div><label class="block text-xs">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded border-gray-300"></div>
                <button class="theme-button theme-button-primary text-sm">Apply</button>
                <a href="{{ route('seller.revenue.export', request()->query()) }}" class="theme-button theme-button-secondary text-sm">Export CSV</a>
            </form>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="theme-card p-3">Total: ${{ number_format($summary['total_revenue'],2) }}</div>
                <div class="theme-card p-3">This month: ${{ number_format($summary['month_revenue'],2) }}</div>
                <div class="theme-card p-3">Avg sale: ${{ number_format($summary['average_sale_price'],2) }}</div>
                <div class="theme-card p-3">Highest: ${{ number_format($summary['highest_sale'],2) }}</div>
            </div>

            <div class="theme-card p-6">
                <table class="min-w-full text-sm">
                    <thead><tr><th class="text-left">Auction</th><th class="text-left">Winning bid</th><th class="text-left">Sale date</th><th class="text-left">Buyer</th><th class="text-left">Reserve</th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr class="border-t"><td>{{ $row->title }}</td><td>${{ number_format($row->winning_bid_amount,2) }}</td><td>{{ optional($row->closed_at)->toDateString() }}</td><td>{{ $row->winner?->name ?: '-' }}</td><td>{{ $row->reserve_met ? 'Met' : 'Not met' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
                {{ $rows->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
