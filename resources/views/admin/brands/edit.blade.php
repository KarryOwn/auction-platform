<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Brand') }}: {{ $brand->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.brands.update', $brand) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $brand->name)" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="website" :value="__('Website URL')" />
                                <x-text-input id="website" class="block mt-1 w-full" type="url" name="website" :value="old('website', $brand->website)" />
                                <x-input-error :messages="$errors->get('website')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-input-label for="logo" :value="__('Logo Image')" />
                                @if($brand->logo_path)
                                    <div class="mt-2 mb-4">
                                        <img src="{{ Storage::url($brand->logo_path) }}" alt="{{ $brand->name }} logo" class="h-20 w-auto object-contain">
                                    </div>
                                @endif
                                <input id="logo" type="file" class="block mt-1 w-full" name="logo" accept="image/*" />
                                <p class="text-sm text-gray-500 mt-1">Leave empty to keep current logo.</p>
                                <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <label for="is_verified" class="inline-flex items-center">
                                    <input id="is_verified" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="is_verified" value="1" {{ old('is_verified', $brand->is_verified) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600">{{ __('Verified Brand') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('admin.brands.index') }}" class="text-sm text-gray-600 hover:text-gray-900 mr-4">Cancel</a>
                            <x-primary-button>
                                {{ __('Update Brand') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>