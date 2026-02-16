<?php

use Illuminate\Support\Facades\Broadcast;

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
