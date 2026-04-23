<script type="module">
    if (window.Echo) {
        window.Echo.private(`conversation.{{ $conversation->id }}`)
            .listen('.message.sent', (e) => {
                const thread = document.getElementById('thread');
                if (!thread) {
                    return;
                }

                const own = e.sender_id === {{ auth()->id() }};
                const wrapper = document.createElement('div');
                wrapper.className = `flex ${own ? 'justify-end' : 'justify-start'}`;

                const bubble = document.createElement('div');
                bubble.className = `max-w-lg rounded-2xl px-4 py-3 shadow-sm ${own ? 'bg-brand text-white' : 'bg-brand-soft text-gray-800'}`;

                const body = document.createElement('p');
                body.className = 'text-sm whitespace-pre-line';
                body.textContent = e.body || '';

                bubble.appendChild(body);
                wrapper.appendChild(bubble);
                thread.appendChild(wrapper);
            });
    }
</script>
