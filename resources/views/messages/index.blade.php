@if(request('layout') === 'minimal')
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Messages</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans antialiased">
        <div class="h-screen flex flex-col pt-4 overflow-y-auto w-full p-4">
            <h2 class="text-xl font-bold mb-4">My Messages</h2>
            <div class="w-full">
                <ul class="divide-y bg-white rounded-lg border shadow-sm">
                    @foreach($conversations as $conversation)
                        <li class="p-3 flex justify-between items-center hover:bg-gray-50 transition">
                            <div class="truncate">
                                <p class="font-medium truncate">{{ $conversation->auction->title }}</p>
                                <p class="text-xs text-gray-500">Seller: {{ $conversation->seller->name }}</p>
                            </div>
                            <a href="{{ route('messages.show', $conversation) }}?layout=minimal" class="text-indigo-600 text-sm font-semibold ml-2 shrink-0 bg-indigo-50 px-3 py-1 rounded">Open</a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4">{{ $conversations->appends(['layout' => 'minimal'])->links() }}</div>
            </div>
        </div>
    </body>
    </html>
@else
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">My Messages</h2></x-slot>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border">
                <div class="p-6">
                    <ul class="divide-y divide-gray-200">
                        @forelse($conversations as $conversation)
                            <li class="py-4 flex justify-between items-center">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $conversation->auction->title }}</p>
                                    <p class="text-sm text-gray-500">Seller: {{ $conversation->seller->name }}</p>
                                </div>
                                <a href="{{ route('messages.show', $conversation) }}" class="text-white bg-indigo-600 px-4 py-2 rounded-md hover:bg-indigo-700 transition">View Discussion</a>
                            </li>
                        @empty
                            <li class="py-4 text-center text-gray-500">No messages yet.</li>
                        @endforelse
                    </ul>
                    <div class="mt-6">
                        {{ $conversations->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
@endif
