<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Attribute') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900" x-data="{ type: '{{ old('type', 'text') }}' }">
                    <form method="POST" action="{{ route('admin.attributes.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="type" :value="__('Type')" />
                                <select id="type" name="type" x-model="type" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="select">Select (Dropdown)</option>
                                    <option value="boolean">Boolean (Yes/No)</option>
                                </select>
                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="unit" :value="__('Unit (Optional)')" />
                                <x-text-input id="unit" class="block mt-1 w-full" type="text" name="unit" :value="old('unit')" placeholder="e.g., kg, cm, GB" />
                                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="sort_order" :value="__('Sort Order')" />
                                <x-text-input id="sort_order" class="block mt-1 w-full" type="number" name="sort_order" :value="old('sort_order', 0)" />
                                <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2" x-show="type === 'select'">
                                <x-input-label for="options" :value="__('Options (JSON array)')" />
                                <textarea id="options" name="options" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm font-mono text-sm" rows="3" placeholder='["Red", "Blue", "Green"]'>{{ old('options') }}</textarea>
                                <p class="text-sm text-gray-500 mt-1">Required for 'select' type. Must be valid JSON array of strings.</p>
                                <x-input-error :messages="$errors->get('options')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-input-label for="category_ids" :value="__('Assign to Categories')" />
                                <select id="category_ids" name="category_ids[]" multiple class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm h-48">
                                    @foreach($categories as $id => $name)
                                        <option value="{{ $id }}" {{ in_array($id, old('category_ids', [])) ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-sm text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple categories.</p>
                                <x-input-error :messages="$errors->get('category_ids')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2 flex gap-6">
                                <label for="is_filterable" class="inline-flex items-center">
                                    <input id="is_filterable" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="is_filterable" value="1" {{ old('is_filterable') ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600">{{ __('Use as Filter in Sidebar') }}</span>
                                </label>

                                <label for="is_required" class="inline-flex items-center">
                                    <input id="is_required" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="is_required" value="1" {{ old('is_required') ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600">{{ __('Required for Sellers') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('admin.attributes.index') }}" class="text-sm text-gray-600 hover:text-gray-900 mr-4">Cancel</a>
                            <x-primary-button>
                                {{ __('Create Attribute') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>