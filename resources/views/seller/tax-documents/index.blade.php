<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tax Documents') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if(session('status'))
                    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                @endif

                <h3 class="text-lg font-medium text-gray-900 mb-4">Generate New Report</h3>
                <form action="{{ route('seller.tax-documents.generate') }}" method="POST" class="flex flex-wrap items-end gap-4" x-data="{ periodType: 'annual' }">
                    @csrf
                    <div>
                        <label for="period_type" class="block text-sm font-medium text-gray-700">Period Type</label>
                        <select name="period_type" id="period_type" x-model="periodType" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="annual">Annual Summary</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>

                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                        <select name="year" id="year" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @foreach($availableYears as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                            @if(empty($availableYears))
                                <option value="{{ now()->year }}">{{ now()->year }}</option>
                            @endif
                        </select>
                    </div>

                    <div x-show="periodType === 'quarterly'" x-cloak>
                        <label for="quarter" class="block text-sm font-medium text-gray-700">Quarter</label>
                        <select name="quarter" id="quarter" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="1">Q1 (Jan - Mar)</option>
                            <option value="2">Q2 (Apr - Jun)</option>
                            <option value="3">Q3 (Jul - Sep)</option>
                            <option value="4">Q4 (Oct - Dec)</option>
                        </select>
                    </div>

                    <div x-show="periodType === 'monthly'" x-cloak>
                        <label for="month" class="block text-sm font-medium text-gray-700">Month</label>
                        <select name="month" id="month" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md">
                        Generate
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Generated Documents</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Sales</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fees & Refunds</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Revenue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Generated On</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($documents as $doc)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $doc->period_label }} ({{ ucfirst($doc->period_type) }})
                                        <div class="text-xs text-gray-500">{{ $doc->period_start->format('M j, Y') }} - {{ $doc->period_end->format('M j, Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ${{ number_format($doc->gross_sales, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                        -${{ number_format($doc->platform_fees_paid + $doc->listing_fees_paid + $doc->refunds_issued, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">
                                        ${{ number_format($doc->net_revenue, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $doc->updated_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('seller.tax-documents.download', $doc) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-semibold">Download PDF</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 text-sm">
                                        No tax documents generated yet. Use the form above to generate your first report.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($documents->hasPages())
                    <div class="px-6 py-4 border-t">
                        {{ $documents->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
