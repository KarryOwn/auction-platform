<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Storefront Settings</h2></x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded shadow-sm">
                @if(session('status'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('seller.storefront.update') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="block text-sm">Slug</label>
                        <input type="text" name="seller_slug" value="{{ old('seller_slug', $user->seller_slug) }}" class="mt-1 w-full rounded border-gray-300" required>
                    </div>
                    <div>
                        <label class="block text-sm">Bio</label>
                        <textarea name="seller_bio" rows="4" class="mt-1 w-full rounded border-gray-300">{{ old('seller_bio', $user->seller_bio) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm">Avatar</label>
                        <input type="file" name="seller_avatar" class="mt-1 w-full rounded border-gray-300">
                    </div>
                    <div x-data="{ policyType: '{{ old('return_policy_type', $user->return_policy_type) }}' }" class="rounded-xl border border-gray-200 p-4">
                        <label class="block text-sm font-medium text-gray-900 mb-3">Return Policy</label>
                        <div class="space-y-2">
                            @foreach(['no_returns' => 'No returns accepted', 'returns_accepted' => 'Returns accepted', 'custom' => 'Custom policy'] as $value => $label)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="radio"
                                           name="return_policy_type"
                                           value="{{ $value }}"
                                           x-model="policyType"
                                           class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div x-show="policyType === 'returns_accepted'" x-cloak class="mt-3">
                            <label for="return_window_days" class="block text-sm font-medium text-gray-700">Return window in days</label>
                            <input id="return_window_days"
                                   type="number"
                                   name="return_window_days"
                                   min="1"
                                   max="90"
                                   value="{{ old('return_window_days', $user->return_window_days) }}"
                                   class="mt-1 w-full rounded border-gray-300">
                        </div>
                        <div x-show="policyType === 'custom'" x-cloak class="mt-3">
                            <label for="return_policy_custom" class="block text-sm font-medium text-gray-700">Custom return policy</label>
                            <textarea id="return_policy_custom"
                                      name="return_policy_custom"
                                      rows="4"
                                      class="mt-1 w-full rounded border-gray-300">{{ old('return_policy_custom', $user->return_policy_custom) }}</textarea>
                        </div>
                        @error('return_policy_type')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('return_window_days')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('return_policy_custom')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
