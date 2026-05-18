<div class="space-y-4">
    @if($conversation->delivery_status)
        <div class="theme-card p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="theme-eyebrow">Delivery status</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $conversation->delivery_status_label }}</p>
                    @if($conversation->delivery_updated_at)
                        <p class="mt-1 text-sm text-gray-500">Updated {{ $conversation->delivery_updated_at->diffForHumans() }}</p>
                    @endif
                    @if($conversation->delivery_note)
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $conversation->delivery_note }}</p>
                    @endif
                </div>

                @isset($deliveryStatusRoute)
                    @if(auth()->id() === $conversation->seller_id)
                        <form method="POST" action="{{ $deliveryStatusRoute }}" class="w-full space-y-2 sm:max-w-sm">
                            @csrf
                            @method('PATCH')
                            <select name="delivery_status" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand focus:ring-brand">
                                @foreach(\App\Models\Conversation::deliveryStatuses() as $status => $label)
                                    <option value="{{ $status }}" @selected($conversation->delivery_status === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <textarea name="delivery_note" rows="2" maxlength="500" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand focus:ring-brand" placeholder="Delivery note">{{ old('delivery_note', $conversation->delivery_note) }}</textarea>
                            <button class="theme-button theme-button-primary text-sm">Update delivery</button>
                        </form>
                    @endif
                @endisset
            </div>
        </div>
    @endif

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
