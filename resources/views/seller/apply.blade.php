<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Become a Seller</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('seller.apply.submit') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Why do you want to sell?</label>
                        <textarea name="reason" rows="6" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('reason') }}</textarea>
                        @error('reason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Experience (optional)</label>
                        <textarea name="experience" rows="4" class="mt-1 block w-full rounded-md border-gray-300">{{ old('experience') }}</textarea>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="accept_terms" value="1" class="rounded border-gray-300" required>
                        <span class="text-sm text-gray-700">I accept seller terms</span>
                    </label>

                    <button class="px-4 py-2 bg-indigo-600 text-white rounded-md">Submit Application</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
