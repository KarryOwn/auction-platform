<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Auction Channels
|--------------------------------------------------------------------------
| Auction price updates and bid events are broadcast on a public channel
| so any visitor can watch in real-time. Use a presence channel if you
| need to show "who is watching".
*/
Broadcast::channel('auctions.{auctionId}', function () {
    return true; // public — any authenticated or guest user may listen
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    return (int) $user->id === (int) $conversation->buyer_id
        || (int) $user->id === (int) $conversation->seller_id;
});

Broadcast::channel('seller.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('buyer.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
