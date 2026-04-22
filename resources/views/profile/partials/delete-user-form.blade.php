<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Account Access') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Deactivate your account for up to 30 days or permanently delete it. Export your data first if you need a copy.') }}
        </p>
    </header>

    <div class="space-y-4">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <h3 class="text-sm font-semibold text-amber-900">{{ __('Deactivate Account') }}</h3>
            <p class="mt-1 text-sm text-amber-800">
                {{ __('Temporarily disable your account. You can reactivate it within 30 days from the reactivation screen.') }}
            </p>

            <form method="post" action="{{ route('profile.deactivate') }}" class="mt-4 space-y-3">
                @csrf

                <div>
                    <x-input-label for="deactivation_password" value="{{ __('Password') }}" class="sr-only" />
                    <x-text-input
                        id="deactivation_password"
                        name="password"
                        type="password"
                        class="mt-1 block w-full"
                        placeholder="{{ __('Confirm password to deactivate') }}"
                    />
                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>

                <x-secondary-button type="submit">{{ __('Deactivate Account') }}</x-secondary-button>
            </form>
        </div>

        <div class="rounded-xl border border-red-200 bg-red-50 p-4">
            <h3 class="text-sm font-semibold text-red-900">{{ __('Permanently Delete Account') }}</h3>
            <p class="mt-1 text-sm text-red-800">
                {{ __('This anonymizes your identity and removes account access permanently.') }}
            </p>

            <x-danger-button
                class="mt-4"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            >{{ __('Delete Account') }}</x-danger-button>
        </div>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 id="confirm-user-deletion-title" class="text-lg font-medium text-gray-900">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
