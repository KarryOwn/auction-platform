<?php

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportEscalationNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('support-chat:127.0.0.1');
});

function fakeGeminiResponse(string $message = 'Here is a support answer.'): void
{
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => $message],
                    ],
                ],
            ]],
        ]),
    ]);
}

test('guest can start and continue their own support conversation', function () {
    fakeGeminiResponse('You can fund your wallet from the dashboard.');

    $start = $this->postJson(route('support.chat.send'), [
        'message' => 'How do I add money to my wallet?',
    ]);

    $start->assertOk()
        ->assertJson([
            'message' => 'You can fund your wallet from the dashboard.',
            'is_ai' => true,
            'can_escalate' => false,
        ]);

    $conversation = SupportConversation::query()->firstOrFail();

    expect($conversation->status)->toBe(SupportConversation::STATUS_AI_HANDLED);
    expect($conversation->messages()->count())->toBe(2);

    fakeGeminiResponse('You can also review your invoice history there.');

    $continue = $this->postJson(route('support.chat.send'), [
        'conversation_id' => $conversation->id,
        'message' => 'Anything else I should check?',
    ]);

    $continue->assertOk()
        ->assertJson([
            'conversation_id' => $conversation->id,
            'can_escalate' => true,
        ]);

    expect($conversation->fresh()->messages()->count())->toBe(4);

    $history = $this->getJson(route('support.chat.show', $conversation));

    $history->assertOk()
        ->assertJsonPath('conversation_id', $conversation->id)
        ->assertJsonCount(4, 'messages');
});

test('other visitors cannot access anonymous conversations they do not own', function () {
    $conversation = SupportConversation::create([
        'status' => SupportConversation::STATUS_OPEN,
        'channel' => 'widget',
        'last_message_at' => now(),
    ]);

    SupportMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'body' => 'Private guest conversation',
        'is_ai' => false,
    ]);

    $this->getJson(route('support.chat.show', $conversation))->assertForbidden();

    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    RateLimiter::clear('support-chat:' . $user->id);

    $this->actingAs($user)
        ->getJson(route('support.chat.show', $conversation))
        ->assertForbidden();
});

test('escalating a support conversation notifies staff', function () {
    Notification::fake();
    fakeGeminiResponse();

    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    $moderator = User::factory()->create([
        'role' => User::ROLE_MODERATOR,
    ]);

    RateLimiter::clear('support-chat:' . $user->id);

    $start = $this->actingAs($user)->postJson(route('support.chat.send'), [
        'message' => 'I need help with a dispute.',
    ]);

    $conversation = SupportConversation::findOrFail($start->json('conversation_id'));

    $response = $this->actingAs($user)->postJson(route('support.chat.escalate', $conversation));

    $response->assertOk()
        ->assertJson([
            'message' => 'Connected to support team. A member will reply shortly.',
        ]);

    expect($conversation->fresh()->status)->toBe(SupportConversation::STATUS_ESCALATED);

    Notification::assertSentTo([$admin, $moderator], SupportEscalationNotification::class);
});

test('staff can reply to and close escalated support conversations', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $conversation = SupportConversation::create([
        'status' => SupportConversation::STATUS_ESCALATED,
        'channel' => 'widget',
        'last_message_at' => now()->subMinute(),
    ]);

    $reply = $this->actingAs($admin)->post(route('admin.support.reply', $conversation), [
        'body' => 'A team member is reviewing your request now.',
    ]);

    $reply->assertRedirect(route('admin.support.show', $conversation));

    expect($conversation->fresh()->assigned_to)->toBe($admin->id);
    expect($conversation->fresh()->status)->toBe(SupportConversation::STATUS_ESCALATED);
    expect($conversation->messages()->latest('id')->first()->role)->toBe('admin');

    $close = $this->actingAs($admin)->post(route('admin.support.close', $conversation));

    $close->assertRedirect(route('admin.support.index'));
    expect($conversation->fresh()->status)->toBe(SupportConversation::STATUS_CLOSED);
});

test('support chat send endpoint is rate limited after ten messages per hour', function () {
    fakeGeminiResponse();

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson(route('support.chat.send'), [
            'message' => "Question {$attempt}",
        ])->assertOk();
    }

    $blocked = $this->postJson(route('support.chat.send'), [
        'message' => 'Question 11',
    ]);

    $blocked->assertStatus(429);
});
