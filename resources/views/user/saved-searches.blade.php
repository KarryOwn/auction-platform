<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Saved Searches') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Your Saved Searches</h3>

                @if($searches->isEmpty())
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No saved searches yet.</p>
                        <p class="text-xs text-gray-400 mt-1">Use the search bar on the <a href="{{ route('auctions.index') }}" class="text-indigo-600 hover:underline">auctions page</a> and click "Save this search".</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($searches as $search)
                            <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition"
                                 x-data="{ deleting: false }">
                                <div class="flex-1 min-w-0">
                                    <a href="{{ $search->getSearchUrl() }}" class="font-medium text-indigo-600 hover:text-indigo-800 truncate block">
                                        {{ $search->name }}
                                    </a>
                                    <div class="text-xs text-gray-400 mt-1">
                                        @if(!empty($search->query_params['q']))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 mr-1">
                                                "{{ $search->query_params['q'] }}"
                                            </span>
                                        @endif
                                        @if(!empty($search->query_params['min_price']) || !empty($search->query_params['max_price']))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 mr-1">
                                                ${{ $search->query_params['min_price'] ?? '0' }} – ${{ $search->query_params['max_price'] ?? '∞' }}
                                            </span>
                                        @endif
                                        @if(!empty($search->query_params['sort']))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                                {{ str_replace('_', ' ', $search->query_params['sort']) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        Saved {{ $search->created_at->diffForHumans() }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 ml-4">
                                    <a href="{{ $search->getSearchUrl() }}"
                                       class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition">
                                        Run
                                    </a>
                                    <button @click="deleting = true"
                                            x-show="!deleting"
                                            class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 text-xs font-semibold rounded-lg hover:bg-red-200 transition">
                                        Delete
                                    </button>
                                    <form x-show="deleting" x-cloak
                                          method="POST" action="{{ route('user.saved-searches.destroy', $search) }}"
                                          class="inline-flex items-center gap-1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition">
                                            Confirm
                                        </button>
                                        <button type="button" @click="deleting = false"
                                                class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-300 transition">
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $searches->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
