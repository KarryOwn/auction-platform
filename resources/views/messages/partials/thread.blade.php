<div class="space-y-4">
    <div id="thread" class="theme-card space-y-3 p-6">
        @foreach($conversation->messages as $message)
            <x-message-bubble :message="$message" :isOwn="$message->sender_id === auth()->id()" />
        @endforeach
    </div>

    <form method="POST" action="{{ $storeRoute }}" class="theme-card space-y-3 p-4">
        @csrf
        <textarea name="body" rows="3" class="w-full rounded-2xl border-gray-200 focus:border-brand focus:ring-brand" placeholder="Write a message..." required></textarea>
        <button class="theme-button theme-button-primary">Send</button>
    </form>
</div>
