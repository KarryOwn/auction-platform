<?php
$file = 'resources/views/seller/messages/show.blade.php';
$content = file_get_contents($file);
$replacement = <<<BLADE
@if(request('layout') === 'minimal')
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Chat</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans antialiased h-screen flex flex-col pt-0">
        <div class="bg-white border-b px-4 py-3 sticky top-0 flex items-center shadow-sm z-10">
            <a href="{{ route('seller.messages.index') }}?layout=minimal" class="mr-3 text-gray-500 hover:text-gray-900 border rounded p-1 shadow-sm shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div class="truncate">
                <h3 class="font-bold text-gray-800 text-sm truncate">{{ \$conversation->auction->title }}</h3>
                <p class="text-xs text-gray-500">{{ \$conversation->buyer->name }}</p>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4 bg-gray-50" id="messages-container">
            @foreach(\$messages as \$message)
                <x-message-bubble :message="\$message" />
            @endforeach
        </div>
        <div class="bg-white p-3 border-t">
            <form action="{{ route('seller.messages.store', \$conversation) }}" method="POST" class="flex items-center space-x-2">
                @csrf
                <input type="hidden" name="layout" value="minimal">
                <input type="text" name="content" required placeholder="Type a message..." class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full py-2 px-3 text-sm">
                <button type="submit" class="bg-indigo-600 border border-transparent rounded-md shadow-sm py-2 px-4 inline-flex justify-center text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shrink-0">Send</button>
            </form>
        </div>
        <script>
            window.onload = function() {
                var container = document.getElementById("messages-container");
                container.scrollTop = container.scrollHeight;
            };
        </script>
    </body>
    </html>
@else
BLADE;

$content = $replacement . "\n" . $content . "\n@endif";
file_put_contents($file, $content);
