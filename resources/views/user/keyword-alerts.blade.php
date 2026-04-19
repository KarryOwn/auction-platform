<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Keyword Alerts') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <x-ui.card>
                <x-slot name="header">
                    <h3 class="text-lg font-medium">Create Alert</h3>
                </x-slot>
                
                <form method="POST" action="{{ route('user.keyword-alerts.store') }}" class="flex gap-4">
                    @csrf
                    <div class="flex-1">
                        <x-text-input id="keyword" name="keyword" type="text" class="w-full" placeholder="e.g. Vintage Rolex..." required />
                        <x-input-error :messages="$errors->get('keyword')" class="mt-2" />
                    </div>
                    <x-ui.button type="submit" variant="primary">Add Alert</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card>
                <x-slot name="header">
                    <h3 class="text-lg font-medium">Your Alerts</h3>
                </x-slot>

                @if($alerts->isEmpty())
                    <div class="text-center py-8 text-gray-500">
                        You have no keyword alerts set up.
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($alerts as $alert)
                            <div class="py-4 flex items-center justify-between" x-data="{ active: {{ $alert->is_active ? 'true' : 'false' }}, toggling: false }">
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $alert->keyword }}</h4>
                                    @if($alert->last_notified_at)
                                        <p class="text-sm text-gray-500">Last notified: {{ $alert->last_notified_at->diffForHumans() }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-2">
                                        <x-ui.badge x-show="active" color="green" size="xs">Active</x-ui.badge>
                                        <x-ui.badge x-show="!active" color="gray" size="xs">Inactive</x-ui.badge>
                                        <button 
                                            @click="
                                                toggling = true;
                                                fetch('{{ route('user.keyword-alerts.toggle', $alert) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                        'Accept': 'application/json'
                                                    }
                                                })
                                                .then(res => res.json())
                                                .then(data => { active = data.active; toggling = false; })
                                            "
                                            :disabled="toggling"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                                            :class="active ? 'bg-indigo-600' : 'bg-gray-200'"
                                        >
                                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="active ? 'translate-x-5' : 'translate-x-0'"></span>
                                        </button>
                                    </div>
                                    <form method="POST" action="{{ route('user.keyword-alerts.destroy', $alert) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="danger" size="sm">Remove</x-ui.button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="mt-4">
                    {{ $alerts->links() }}
                </div>
            </x-ui.card>
            
        </div>
    </div>
</x-app-layout>
