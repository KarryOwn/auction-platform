@props(['message', 'isOwn' => false])

<div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
    <div class="max-w-lg px-3 py-2 rounded-lg {{ $isOwn ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800' }}">
        <p class="text-sm whitespace-pre-line">{{ $message->body }}</p>
        <p class="text-[11px] opacity-75 mt-1">{{ $message->created_at->diffForHumans() }}</p>
    </div>
</div>
