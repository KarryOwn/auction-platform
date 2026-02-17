<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Conversation: {{ $conversation->auction->title }}</h2></x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div id="thread" class="bg-white p-6 rounded shadow-sm space-y-3">
                @foreach($conversation->messages as $message)
                    <x-message-bubble :message="$message" :isOwn="$message->sender_id === auth()->id()" />
                @endforeach
            </div>

            <form method="POST" action="{{ route('seller.messages.store', $conversation) }}" class="bg-white p-4 rounded shadow-sm space-y-2">
                @csrf
                <textarea name="body" rows="3" class="w-full rounded border-gray-300" required></textarea>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Send</button>
            </form>
        </div>
    </div>

    <script type="module">
        Echo.private(`conversation.{{ $conversation->id }}`)
            .listen('.message.sent', (e) => {
                const thread = document.getElementById('thread');
                const own = e.sender_id === {{ auth()->id() }};
                const wrapper = document.createElement('div');
                wrapper.className = `flex ${own ? 'justify-end' : 'justify-start'}`;
                wrapper.innerHTML = `<div class="max-w-lg px-3 py-2 rounded-lg ${own ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800'}"><p class="text-sm">${e.body}</p></div>`;
                thread.appendChild(wrapper);
            });
    </script>
</x-app-layout>
