@php($isMinimal = request('layout') === 'minimal')

@if($isMinimal)
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Conversation</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="theme-shell font-sans antialiased">
        <div class="min-h-screen p-4">
            <div class="mx-auto max-w-4xl space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="theme-eyebrow">Buyer conversation</p>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $conversation->auction->title }}</h1>
                    </div>
                    <a href="{{ route('seller.messages.index') }}?layout=minimal" class="theme-link text-sm">Back</a>
                </div>

                @include('messages.partials.thread', ['conversation' => $conversation, 'storeRoute' => route('seller.messages.store', $conversation)])
            </div>
        </div>

        @include('messages.partials.realtime-script', ['conversation' => $conversation])
    </body>
    </html>
@else
    <x-app-layout>
        <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Conversation: {{ $conversation->auction->title }}</h2></x-slot>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                @include('messages.partials.thread', ['conversation' => $conversation, 'storeRoute' => route('seller.messages.store', $conversation)])
            </div>
        </div>

        @include('messages.partials.realtime-script', ['conversation' => $conversation])
    </x-app-layout>
@endif
