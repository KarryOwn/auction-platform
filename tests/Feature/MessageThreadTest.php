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

test('buyer can load minimal message inbox in chat drawer', function () {
    [$buyer, $seller, $conversation] = createMessageThread();

    $response = $this->actingAs($buyer)->get(route('messages.index', [
        'layout' => 'minimal',
    ]));

    $response->assertOk()
        ->assertSee('My Messages')
        ->assertSee($conversation->auction->title)
        ->assertSee('Seller: ' . $seller->name)
        ->assertSee('1 message')
        ->assertSee(route('messages.show', $conversation) . '?layout=minimal', false);
});

test('buyer minimal message inbox shows an empty state', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->get(route('messages.index', ['layout' => 'minimal']))
        ->assertOk()
        ->assertSee('No messages yet.')
        ->assertSee('Start a conversation from an auction page and it will appear here.');
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

test('seller can load minimal message inbox in chat drawer', function () {
    [$buyer, $seller, $conversation] = createMessageThread();

    $response = $this->actingAs($seller)->get(route('seller.messages.index', [
        'layout' => 'minimal',
    ]));

    $response->assertOk()
        ->assertSee('Buyer Messages')
        ->assertSee($conversation->auction->title)
        ->assertSee('Buyer: ' . $buyer->name)
        ->assertSee('1 message')
        ->assertSee(route('seller.messages.show', $conversation) . '?layout=minimal', false);
});

test('seller minimal message inbox shows an empty state', function () {
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);

    $this->actingAs($seller)
        ->get(route('seller.messages.index', ['layout' => 'minimal']))
        ->assertOk()
        ->assertSee('No messages yet.')
        ->assertSee('Buyer conversations will appear here when shoppers contact you.');
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
