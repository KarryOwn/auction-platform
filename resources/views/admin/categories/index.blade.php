<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Categories') }}
            </h2>
            <a href="{{ route('admin.categories.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700">
                Add Category
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Featured</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($categories as $category)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="ml-{{ $category->depth * 4 }}">
                                                    @if($category->icon)
                                                        <i class="{{ $category->icon }} text-gray-400 mr-2"></i>
                                                    @endif
                                                    <span class="text-sm font-medium text-gray-900">{{ $category->name }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $category->slug }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <form action="{{ route('admin.categories.toggle', $category) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($category->is_currently_featured)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                                    Featured
                                                </span>
                                                @if($category->featured_until)
                                                    <div class="text-xs text-gray-500 mt-1">Until {{ $category->featured_until->format('M d, Y H:i') }}</div>
                                                @endif
                                            @else
                                                <span class="text-gray-400 text-xs">Not featured</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            @if($category->commission_rate !== null)
                                                {{ number_format((float) $category->commission_rate * 100, 2) }}%
                                                <span class="text-xs text-gray-400">(override)</span>
                                            @else
                                                {{ number_format($category->effective_commission_percent, 2) }}%
                                                <span class="text-xs text-gray-400">(inherited)</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div x-data="categoryFeatureManager({
                                                featureUrl: '{{ route('admin.categories.feature', $category) }}',
                                                unfeatureUrl: '{{ route('admin.categories.unfeature', $category) }}',
                                                bannerUrl: '{{ route('admin.categories.featured-banner', $category) }}',
                                                csrf: '{{ csrf_token() }}',
                                                initialFeatured: @js((bool) $category->is_currently_featured),
                                                initialFeaturedUntil: @js($category->featured_until?->format('M d, Y H:i')),
                                                initialSortOrder: @js($category->featured_sort_order),
                                                initialTagline: @js($category->featured_tagline),
                                            })" class="inline-flex items-center gap-3">
                                                <button type="button" class="text-amber-600 hover:text-amber-800" @click="toggleForm">
                                                    <span x-text="featured ? 'Manage Feature' : 'Feature'"></span>
                                                </button>
                                                <div x-show="showForm" x-cloak class="absolute right-8 mt-2 w-80 rounded-lg border border-gray-200 bg-white p-4 shadow-xl z-20 text-left">
                                                    <div class="space-y-3">
                                                        <label class="block text-xs text-gray-600">
                                                            Duration (hours)
                                                            <input x-model.number="duration" type="number" min="1" max="8760" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs text-gray-600">
                                                            Sort order
                                                            <input x-model.number="sortOrder" type="number" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs text-gray-600">
                                                            Tagline
                                                            <input x-model="tagline" type="text" maxlength="200" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs text-gray-600">
                                                            Featured banner
                                                            <input x-ref="banner" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-gray-600">
                                                        </label>
                                                        <div class="flex flex-wrap gap-2">
                                                            <button type="button" class="inline-flex items-center justify-center min-h-11 px-3 py-2 rounded-md bg-amber-600 text-white text-xs hover:bg-amber-700 disabled:opacity-60" :disabled="submitting" @click="submitFeature">
                                                                Save
                                                            </button>
                                                            <button type="button" x-show="featured" class="inline-flex items-center justify-center min-h-11 px-3 py-2 rounded-md bg-red-50 text-red-700 text-xs hover:bg-red-100 disabled:opacity-60" :disabled="submitting" @click="removeFeature">
                                                                Remove
                                                            </button>
                                                        </div>
                                                        <p class="text-xs text-gray-500" x-show="message" x-text="message"></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        if (!window.categoryFeatureManager) {
            window.categoryFeatureManager = function (config) {
                return {
                    featured: Boolean(config.initialFeatured),
                    featuredUntil: config.initialFeaturedUntil || null,
                    sortOrder: config.initialSortOrder ?? 0,
                    tagline: config.initialTagline || '',
                    duration: 168,
                    showForm: false,
                    submitting: false,
                    message: '',
                    toggleForm() {
                        this.showForm = !this.showForm;
                    },
                    async submitFeature() {
                        this.submitting = true;
                        this.message = '';

                        try {
                            const featureResponse = await fetch(config.featureUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                },
                                body: JSON.stringify({
                                    duration_hours: this.duration,
                                    featured_sort_order: this.sortOrder || 0,
                                    featured_tagline: this.tagline || null,
                                }),
                            });

                            const featureData = await featureResponse.json();
                            if (!featureResponse.ok) {
                                this.message = featureData.message || 'Unable to feature category.';
                                return;
                            }

                            const file = this.$refs.banner?.files?.[0];
                            if (file) {
                                const body = new FormData();
                                body.append('banner', file);

                                const bannerResponse = await fetch(config.bannerUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': config.csrf,
                                    },
                                    body,
                                });

                                const bannerData = await bannerResponse.json();
                                if (!bannerResponse.ok) {
                                    this.message = bannerData.message || 'Feature saved, but banner upload failed.';
                                    this.featured = true;
                                    return;
                                }
                            }

                            this.featured = true;
                            this.message = featureData.message || 'Category featured.';
                            window.location.reload();
                        } catch (error) {
                            this.message = 'Request failed.';
                        } finally {
                            this.submitting = false;
                        }
                    },
                    async removeFeature() {
                        this.submitting = true;
                        this.message = '';

                        try {
                            const response = await fetch(config.unfeatureUrl, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                },
                            });

                            const data = await response.json();
                            if (!response.ok) {
                                this.message = data.message || 'Unable to remove featured status.';
                                return;
                            }

                            this.featured = false;
                            this.message = data.message || 'Featured status removed.';
                            window.location.reload();
                        } catch (error) {
                            this.message = 'Request failed.';
                        } finally {
                            this.submitting = false;
                        }
                    },
                };
            };
        }
    </script>
    @endpush
</x-app-layout>
