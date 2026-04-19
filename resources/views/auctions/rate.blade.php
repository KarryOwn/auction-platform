<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Rate Transaction</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900">Rate {{ $ratee->name }}</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Auction: <span class="font-medium">{{ $auction->title }}</span>
                </p>

                @if ($errors->any())
                    <div class="mt-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('auctions.rate.store', $auction) }}" class="mt-6 space-y-6"
                      x-data="{ rating: {{ (int) old('score', 0) }}, hover: 0 }">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Your rating</label>
                        <input type="hidden" name="score" :value="rating">

                        <div class="flex items-center gap-1">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button"
                                        @mouseenter="hover = {{ $i }}"
                                        @mouseleave="hover = 0"
                                        @click="rating = {{ $i }}"
                                        aria-label="Select {{ $i }} {{ \Illuminate\Support\Str::plural('star', $i) }}"
                                        class="p-1 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
                                    <svg class="w-9 h-9 transition"
                                         :class="(hover >= {{ $i }} || rating >= {{ $i }}) ? 'text-amber-400' : 'text-gray-300'"
                                         fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </button>
                            @endfor
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Click a star to choose a score from 1 to 5.</p>
                    </div>

                    <div>
                        <label for="comment" class="block text-sm font-medium text-gray-700 mb-2">Comment (optional)</label>
                        <textarea id="comment"
                                  name="comment"
                                  rows="4"
                                  maxlength="500"
                                  class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Share your experience...">{{ old('comment') }}</textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-ui.button type="submit" variant="primary">Submit Rating</x-ui.button>
                        <a href="{{ route('auctions.show', $auction) }}" class="text-sm text-gray-600 hover:text-gray-800">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>