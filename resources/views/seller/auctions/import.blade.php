<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Import Auctions from CSV</h2>
            <a href="{{ route('seller.auctions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700">Back to My Auctions</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-ui.card>
                <p class="text-sm text-gray-600 dark:text-gray-300">Upload a CSV file to create multiple auctions as drafts.</p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a href="{{ route('seller.auctions.import.template') }}" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                        Download CSV Template
                    </a>
                    <a href="{{ route('seller.auctions.index') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100">
                        View created drafts
                    </a>
                </div>
            </x-ui.card>

            <x-ui.card>
                <form method="POST" action="{{ route('seller.auctions.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="file" value="CSV File" />
                        <input id="file" name="file" type="file" accept=".csv,.txt" class="mt-1 block w-full border border-gray-300 rounded-md p-2" required>
                        <x-input-error class="mt-2" :messages="$errors->get('file')" />
                    </div>

                    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                        <p>Required columns: <span class="font-medium">title, description, starting_price, end_time</span></p>
                        <p>Optional columns: reserve_price, min_bid_increment, start_time, condition, tags</p>
                    </div>

                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                            Import CSV
                        </button>
                    </div>
                </form>
            </x-ui.card>

            @if(session('import_created') !== null)
                <x-ui.card>
                    <p class="text-sm text-green-700 dark:text-green-400 font-medium">
                        {{ session('import_created') }} auctions created.
                    </p>

                    @php($importErrors = session('import_errors', []))
                    @if(!empty($importErrors))
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                        <th class="py-2 pr-4">Row</th>
                                        <th class="py-2">Error message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($importErrors as $error)
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <td class="py-2 pr-4 text-gray-800 dark:text-gray-200">{{ $error['row'] ?? '-' }}</td>
                                            <td class="py-2 text-red-700 dark:text-red-400">{{ $error['message'] ?? 'Unknown error' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>
            @endif
        </div>
    </div>
</x-app-layout>
