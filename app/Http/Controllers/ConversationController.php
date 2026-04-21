<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\SendMessageRequest;
use App\Models\Auction;
use App\Models\Conversation;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\RedirectResponse;

class ConversationController extends Controller
{
    public function start(SendMessageRequest $request, Auction $auction): RedirectResponse
    {
        $buyer = $request->user();

        if ($auction->user_id === $buyer->id) {
            return back()->withErrors(['message' => 'You cannot message yourself.']);
        }

        if ($buyer->hasBlocked($auction->user_id) || $buyer->isBlockedBy($auction->user_id)) {
            return back()->withErrors(['message' => 'You cannot message this user.']);
        }

        $conversation = Conversation::firstOrCreate(
            [
                'auction_id' => $auction->id,
                'buyer_id' => $buyer->id,
            ],
            [
                'seller_id' => $auction->user_id,
                'buyer_read_at' => now(),
            ],
        );

        if ($conversation->is_closed) {
            return back()->withErrors(['message' => 'Conversation is closed.']);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'body' => $request->input('body'),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'buyer_read_at' => now(),
        ]);

        $conversation->seller?->notify(new NewMessageNotification($conversation->load('auction'), $message));

        broadcast(new MessageSent($conversation, $message))->toOthers();

        return redirect()->route('messages.show', $conversation);
    }
}
