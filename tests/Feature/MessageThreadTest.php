<?php

use App\Models\Auction;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

test('buyer can load minimal message thread', function () {
    [$buyer, $seller, $conversation] = createMessageThread();

    $response = $this->actingAs($buyer)->get(route('messages.show', [
        'conversation' => $conversation,
        'layout' => 'minimal',
    ]));

    $response->assertOk()
        ->assertSee('Conversation')
        ->assertSee($conversation->auction->title)
        ->assertSee('Hello from buyer')
        ->assertSee('Write a message...')
        ->assertSee(route('messages.index') . '?layout=minimal', false);
});

test('seller can load minimal message thread', function () {
    [$buyer, $seller, $conversation] = createMessageThread();

    $response = $this->actingAs($seller)->get(route('seller.messages.show', [
        'conversation' => $conversation,
        'layout' => 'minimal',
    ]));

    $response->assertOk()
        ->assertSee('Buyer conversation')
        ->assertSee($conversation->auction->title)
        ->assertSee('Hello from buyer')
        ->assertSee('Write a message...')
        ->assertSee(route('seller.messages.index') . '?layout=minimal', false);
});

function createMessageThread(): array
{
    $buyer = User::factory()->create();
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Vintage Camera Kit',
    ]);

    $conversation = Conversation::create([
        'auction_id' => $auction->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'last_message_at' => now(),
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $buyer->id,
        'body' => 'Hello from buyer',
    ]);

    return [$buyer, $seller, $conversation->load('auction')];
}
