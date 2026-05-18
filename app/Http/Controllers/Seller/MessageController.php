<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\MessageController as BaseMessageController;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageController extends BaseMessageController
{
    public function index(Request $request)
    {
        $conversations = Conversation::query()
            ->where('seller_id', $request->user()->id)
            ->with(['auction:id,title', 'buyer:id,name'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return view('seller.messages.index', compact('conversations'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->seller_id === $request->user()->id || $conversation->buyer_id === $request->user()->id, 403);

        $conversation->load(['messages.sender:id,name', 'auction:id,title']);
        $conversation->update(['seller_read_at' => now()]);

        return view('seller.messages.show', compact('conversation'));
    }

    public function updateDeliveryStatus(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->seller_id === $request->user()->id, 403);
        abort_unless($conversation->delivery_status !== null, 404);

        $validated = $request->validate([
            'delivery_status' => ['required', Rule::in(array_keys(Conversation::deliveryStatuses()))],
            'delivery_note' => ['nullable', 'string', 'max:500'],
        ]);

        $conversation->update([
            'delivery_status' => $validated['delivery_status'],
            'delivery_note' => $validated['delivery_note'] ?? null,
            'delivery_updated_at' => now(),
        ]);

        return back()->with('success', 'Delivery status updated.');
    }
}
