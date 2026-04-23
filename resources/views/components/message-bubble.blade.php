@props(['message', 'isOwn' => false])

<div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
    <div class="max-w-lg rounded-2xl px-4 py-3 shadow-sm {{ $isOwn ? 'bg-brand text-white' : 'bg-brand-soft text-gray-800' }}">
        <p class="text-sm whitespace-pre-line">{{ $message->body }}</p>
        <p class="text-[11px] opacity-75 mt-1">{{ $message->created_at->diffForHumans() }}</p>
    </div>
</div>
