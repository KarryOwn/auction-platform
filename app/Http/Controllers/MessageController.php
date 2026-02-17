<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where('buyer_id', $user->id)
            ->with(['auction:id,title', 'seller:id,name,seller_slug'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return view('messages.index', compact('conversations'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->buyer_id === $request->user()->id || $conversation->seller_id === $request->user()->id, 403);

        $conversation->load(['messages.sender:id,name', 'auction:id,title']);

        if ($conversation->buyer_id === $request->user()->id) {
            $conversation->update(['buyer_read_at' => now()]);
        }

        return view('messages.show', compact('conversation'));
    }

    public function store(SendMessageRequest $request, Conversation $conversation)
    {
        abort_unless($conversation->buyer_id === $request->user()->id || $conversation->seller_id === $request->user()->id, 403);

        if ($conversation->is_closed) {
            return back()->withErrors(['message' => 'Conversation is closed.']);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->input('body'),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'buyer_read_at' => $request->user()->id === $conversation->buyer_id ? now() : $conversation->buyer_read_at,
            'seller_read_at' => $request->user()->id === $conversation->seller_id ? now() : $conversation->seller_read_at,
        ]);

        $recipient = $request->user()->id === $conversation->buyer_id ? $conversation->seller : $conversation->buyer;
        $recipient?->notify(new NewMessageNotification($conversation->load('auction'), $message));

        broadcast(new MessageSent($conversation, $message))->toOthers();

        return back();
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->buyer_id === $request->user()->id || $conversation->seller_id === $request->user()->id, 403);

        if ($request->user()->id === $conversation->buyer_id) {
            $conversation->update(['buyer_read_at' => now()]);
        } else {
            $conversation->update(['seller_read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}
