<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Browse Categories</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach($categories as $category)
                    <a href="{{ route('categories.show', $category) }}"
                       class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow p-6 text-center group">
                        @if($category->image_path)
                            <img src="{{ Storage::url($category->image_path) }}" alt="{{ $category->name }}"
                                 class="w-20 h-20 mx-auto mb-4 object-cover rounded-lg">
                        @elseif($category->icon)
                            <div class="w-20 h-20 mx-auto mb-4 flex items-center justify-center bg-indigo-50 rounded-lg group-hover:bg-indigo-100 transition-colors">
                                <i class="{{ $category->icon }} text-3xl text-indigo-600"></i>
                            </div>
                        @else
                            <div class="w-20 h-20 mx-auto mb-4 flex items-center justify-center bg-gray-100 rounded-lg">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                        @endif

                        <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">{{ $category->name }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ $category->auctions_count }} {{ Str::plural('auction', $category->auctions_count) }}</p>

                        @if($category->description)
                            <p class="text-xs text-gray-400 mt-2 line-clamp-2">{{ $category->description }}</p>
                        @endif
                    </a>
                @endforeach
            </div>

            @if($categories->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                    <p class="text-gray-400 text-lg">No categories available yet.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
