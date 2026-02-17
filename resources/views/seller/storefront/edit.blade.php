<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Storefront Settings</h2></x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded shadow-sm">
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
                    <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
