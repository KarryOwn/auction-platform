<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Open Dispute</h2>
            <a href="{{ route('user.won-auctions') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Won Auctions</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900">Auction: {{ $auction->title }}</h3>
                <p class="text-sm text-gray-500 mt-1">Submit evidence and details so the admin team can review this case.</p>

                <form method="POST" action="{{ route('disputes.store', $auction) }}" class="mt-6 space-y-4">
                    @csrf

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Dispute Type</label>
                        <select id="type" name="type" class="w-full rounded-md border-gray-300 text-sm" required>
                            <option value="item_not_received" @selected(old('type') === 'item_not_received')>Item not received</option>
                            <option value="not_as_described" @selected(old('type') === 'not_as_described')>Not as described</option>
                            <option value="non_payment" @selected(old('type') === 'non_payment')>Non payment</option>
                            <option value="other" @selected(old('type') === 'other')>Other</option>
                        </select>
                        @error('type')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="6" class="w-full rounded-md border-gray-300 text-sm" required>{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Evidence URLs (optional)</label>
                        <input type="url" name="evidence_urls[]" class="w-full rounded-md border-gray-300 text-sm mb-2" placeholder="https://example.com/evidence-1">
                        <input type="url" name="evidence_urls[]" class="w-full rounded-md border-gray-300 text-sm mb-2" placeholder="https://example.com/evidence-2">
                        <input type="url" name="evidence_urls[]" class="w-full rounded-md border-gray-300 text-sm" placeholder="https://example.com/evidence-3">
                        @error('evidence_urls.*')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700">
                        Submit Dispute
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
