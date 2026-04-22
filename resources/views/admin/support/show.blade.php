<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Support Conversation #{{ $conversation->id }}</h2>
                <p class="text-sm text-gray-500">
                    {{ $conversation->user ? $conversation->user->name . ' (' . $conversation->user->email . ')' : 'Anonymous widget user' }}
                </p>
            </div>
            <a href="{{ route('admin.support.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Back to inbox</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[1.6fr,0.8fr]">
                <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">Messages</h3>
                    </div>
                    <div class="max-h-[32rem] space-y-4 overflow-y-auto bg-slate-50 px-6 py-6">
                        @forelse($conversation->messages as $message)
                            <div class="{{ $message->role === 'user' ? 'flex justify-end' : 'flex justify-start' }}">
                                <div class="{{ $message->role === 'user' ? 'max-w-[80%] rounded-2xl rounded-br-md bg-indigo-600 px-4 py-3 text-sm text-white' : 'max-w-[80%] rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm' }}">
                                    <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide {{ $message->role === 'user' ? 'text-indigo-100' : 'text-slate-400' }}">
                                        {{ $message->role === 'assistant' ? ($message->is_ai ? 'AI assistant' : 'Assistant') : ucfirst($message->role) }}
                                    </div>
                                    <p class="whitespace-pre-line">{{ $message->body }}</p>
                                    <div class="mt-2 text-[11px] {{ $message->role === 'user' ? 'text-indigo-100' : 'text-slate-400' }}">
                                        {{ $message->created_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">No messages yet.</div>
                        @endforelse
                    </div>
                </section>

                <section class="space-y-6">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Conversation</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Status</dt>
                                <dd class="font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $conversation->status)) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Channel</dt>
                                <dd class="font-medium text-gray-900">{{ ucfirst($conversation->channel) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Assigned to</dt>
                                <dd class="font-medium text-gray-900">{{ $conversation->assignee?->name ?? 'Unassigned' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Last activity</dt>
                                <dd class="font-medium text-gray-900">{{ $conversation->last_message_at?->diffForHumans() ?? 'No activity yet' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Reply</h3>
                        <form method="POST" action="{{ route('admin.support.reply', $conversation) }}" class="mt-4 space-y-3">
                            @csrf
                            <textarea name="body" rows="5" maxlength="2000" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Reply to the user..." required></textarea>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                Send Reply
                            </button>
                        </form>
                    </div>

                    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-red-900">Close Conversation</h3>
                        <p class="mt-2 text-sm text-red-800">Marks this support conversation as closed and removes it from the default inbox queue.</p>
                        <form method="POST" action="{{ route('admin.support.close', $conversation) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                Close Conversation
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
