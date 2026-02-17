<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">My Messages</h2></x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded shadow-sm">
                <ul class="divide-y">
                    @foreach($conversations as $conversation)
                        <li class="py-3 flex justify-between items-center">
                            <div>
                                <p class="font-medium">{{ $conversation->auction->title }}</p>
                                <p class="text-sm text-gray-600">Seller: {{ $conversation->seller->name }}</p>
                            </div>
                            <a href="{{ route('messages.show', $conversation) }}" class="text-indigo-600">Open</a>
                        </li>
                    @endforeach
                </ul>
                {{ $conversations->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
