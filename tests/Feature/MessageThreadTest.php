<?php

use App\Models\Auction;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Facades\Notification;

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
        ->assertSee(route('messages.index').'?layout=minimal', false);
});

test('buyer can load minimal message inbox in chat drawer', function () {
    [$buyer, $seller, $conversation] = createMessageThread();

    $response = $this->actingAs($buyer)->get(route('messages.index', [
        'layout' => 'minimal',
    ]));

    $response->assertOk()
        ->assertSee('My Messages')
        ->assertSee($conversation->auction->title)
        ->assertSee('Seller: '.$seller->name)
        ->assertSee('1 message')
        ->assertSee(route('messages.show', $conversation).'?layout=minimal', false);
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
        ->assertSee(route('seller.messages.index').'?layout=minimal', false);
});

test('seller can load minimal message inbox in chat drawer', function () {
    [$buyer, $seller, $conversation] = createMessageThread();

    $response = $this->actingAs($seller)->get(route('seller.messages.index', [
        'layout' => 'minimal',
    ]));

    $response->assertOk()
        ->assertSee('Buyer Messages')
        ->assertSee($conversation->auction->title)
        ->assertSee('Buyer: '.$buyer->name)
        ->assertSee('1 message')
        ->assertSee(route('seller.messages.show', $conversation).'?layout=minimal', false);
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

test('message recipient receives email channel when message email preference is enabled', function () {
    Notification::fake();

    [$buyer, $seller, $conversation] = createMessageThread();

    $this->actingAs($buyer)
        ->post(route('messages.store', $conversation), [
            'body' => 'Can you confirm the lens condition?',
        ])
        ->assertRedirect();

    Notification::assertSentTo($seller, NewMessageNotification::class, function (NewMessageNotification $notification) use ($seller) {
        return in_array('mail', $notification->via($seller), true);
    });
});

test('message recipient does not receive email channel when message email preference is disabled', function () {
    Notification::fake();

    [$buyer, $seller, $conversation] = createMessageThread();

    $preferences = User::DEFAULT_NOTIFICATION_PREFERENCES;
    $preferences['messages']['email'] = false;
    $seller->update(['notification_preferences' => $preferences]);

    $this->actingAs($buyer)
        ->post(route('messages.store', $conversation), [
            'body' => 'Can you confirm the serial number?',
        ])
        ->assertRedirect();

    Notification::assertSentTo($seller, NewMessageNotification::class, function (NewMessageNotification $notification) use ($seller) {
        return ! in_array('mail', $notification->via($seller), true)
            && in_array('database', $notification->via($seller), true);
    });
});

test('buyer can see delivery status on won item thread', function () {
    [$buyer, $seller, $conversation] = createMessageThread([
        'delivery_status' => Conversation::DELIVERY_PENDING,
        'delivery_updated_at' => now(),
        'delivery_note' => 'Seller is preparing the item.',
    ]);

    $this->actingAs($buyer)
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSee('Delivery status')
        ->assertSee('Pending')
        ->assertSee('Seller is preparing the item.')
        ->assertDontSee('Update delivery');
});

test('seller can update delivery status on won item thread', function () {
    [$buyer, $seller, $conversation] = createMessageThread([
        'delivery_status' => Conversation::DELIVERY_PENDING,
        'delivery_updated_at' => now(),
    ]);

    $this->actingAs($seller)
        ->patch(route('seller.messages.delivery-status', $conversation), [
            'delivery_status' => Conversation::DELIVERY_SHIPPED,
            'delivery_note' => 'Tracking will be shared shortly.',
        ])
        ->assertRedirect();

    $conversation->refresh();

    expect($conversation->delivery_status)->toBe(Conversation::DELIVERY_SHIPPED)
        ->and($conversation->delivery_note)->toBe('Tracking will be shared shortly.')
        ->and($conversation->delivery_updated_at)->not->toBeNull();
});

test('unrelated seller cannot update delivery status on another thread', function () {
    [$buyer, $seller, $conversation] = createMessageThread([
        'delivery_status' => Conversation::DELIVERY_PENDING,
        'delivery_updated_at' => now(),
    ]);

    $otherSeller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ]);

    $this->actingAs($otherSeller)
        ->patch(route('seller.messages.delivery-status', $conversation), [
            'delivery_status' => Conversation::DELIVERY_SHIPPED,
        ])
        ->assertForbidden();

    expect($conversation->fresh()->delivery_status)->toBe(Conversation::DELIVERY_PENDING);
});

test('buyer cannot access seller delivery status update route', function () {
    [$buyer, $seller, $conversation] = createMessageThread([
        'delivery_status' => Conversation::DELIVERY_PENDING,
        'delivery_updated_at' => now(),
    ]);

    $this->actingAs($buyer)
        ->patch(route('seller.messages.delivery-status', $conversation), [
            'delivery_status' => Conversation::DELIVERY_SHIPPED,
        ])
        ->assertRedirect(route('seller.apply.form'));

    expect($conversation->fresh()->delivery_status)->toBe(Conversation::DELIVERY_PENDING);
});

test('buyer can view paid won auctions page with delivery thread link', function () {
    [$buyer, $seller, $conversation] = createMessageThread([
        'delivery_status' => Conversation::DELIVERY_PENDING,
        'delivery_updated_at' => now(),
    ]);

    $conversation->auction->update([
        'status' => Auction::STATUS_COMPLETED,
        'winner_id' => $buyer->id,
        'winning_bid_amount' => 125,
        'payment_status' => 'paid',
        'closed_at' => now(),
    ]);

    $this->actingAs($buyer)
        ->get(route('user.won-auctions', ['tab' => 'paid']))
        ->assertOk()
        ->assertSee('Paid')
        ->assertSee('Open Thread')
        ->assertSee(route('messages.show', $conversation), false);
});

function createMessageThread(array $conversationOverrides = []): array
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

    $conversation = Conversation::create(array_merge([
        'auction_id' => $auction->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
        'last_message_at' => now(),
    ], $conversationOverrides));

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $buyer->id,
        'body' => 'Hello from buyer',
    ]);

    return [$buyer, $seller, $conversation->load('auction')];
}
