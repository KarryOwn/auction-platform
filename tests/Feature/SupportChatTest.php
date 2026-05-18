<?php

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\SupportEscalationNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('support-chat:127.0.0.1');
});

function fakeOpenRouterResponse(string $message = 'Here is a support answer.'): void
{
    config([
        'services.openrouter.api_key' => 'test-openrouter-key',
        'services.openrouter.model' => 'tencent/hy3-preview:free',
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => $message,
                ],
            ]],
        ]),
    ]);
}

function clearOpenRouterConfiguration(): void
{
    config(['services.openrouter.api_key' => null]);
}

test('guest can start and continue their own support conversation', function () {
    fakeOpenRouterResponse('You can fund your wallet from the dashboard.');

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

    fakeOpenRouterResponse('You can also review your invoice history there.');

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

    RateLimiter::clear('support-chat:'.$user->id);

    $this->actingAs($user)
        ->getJson(route('support.chat.show', $conversation))
        ->assertForbidden();
});

test('escalating a support conversation notifies admins', function () {
    Notification::fake();
    fakeOpenRouterResponse();

    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
    ]);

    RateLimiter::clear('support-chat:'.$user->id);

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

    Notification::assertSentTo($admin, SupportEscalationNotification::class, function (SupportEscalationNotification $notification) use ($admin) {
        return in_array('mail', $notification->via($admin), true);
    });
    Notification::assertNotSentTo($seller, SupportEscalationNotification::class);
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

test('staff can fetch support conversation messages for live refresh', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $conversation = SupportConversation::create([
        'status' => SupportConversation::STATUS_ESCALATED,
        'channel' => 'widget',
        'last_message_at' => now()->subMinute(),
    ]);

    SupportMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'body' => 'I still need help.',
        'is_ai' => false,
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.support.messages', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation_id', $conversation->id)
        ->assertJsonPath('status', SupportConversation::STATUS_ESCALATED)
        ->assertJsonPath('messages.0.body', 'I still need help.');
});

test('support escalation notification links to admin support inbox conversation', function () {
    $conversation = SupportConversation::create([
        'status' => SupportConversation::STATUS_ESCALATED,
        'channel' => 'widget',
        'last_message_at' => now(),
    ]);

    $payload = (new SupportEscalationNotification($conversation))->toArray(User::factory()->make([
        'role' => User::ROLE_ADMIN,
    ]));

    expect($payload['type'])->toBe('support_escalation')
        ->and($payload['support_conversation_id'])->toBe($conversation->id)
        ->and($payload['url'])->toBe(route('admin.support.show', $conversation));
});

test('support chat send endpoint is rate limited after ten messages per hour', function () {
    fakeOpenRouterResponse();

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

test('support chat returns local fallback when openrouter is not configured', function () {
    clearOpenRouterConfiguration();
    Http::fake();

    $response = $this->postJson(route('support.chat.send'), [
        'message' => 'How do I add money to my wallet?',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Wallet actions are available after signing in from your account wallet page. If a payment or balance looks wrong, click "Talk to a human" so support can review the transaction.');

    Http::assertNothingSent();
});

test('support chat gives logged in users account fallback instead of generic unavailable message', function () {
    clearOpenRouterConfiguration();
    Http::fake();

    $user = User::factory()->create([
        'role' => User::ROLE_USER,
        'wallet_balance' => 125.50,
        'seller_application_status' => 'pending',
    ]);

    RateLimiter::clear('support-chat:'.$user->id);

    $response = $this->actingAs($user)->postJson(route('support.chat.send'), [
        'message' => 'Account',
    ]);

    $response->assertOk();

    expect($response->json('message'))
        ->toContain('Your account is active as a user')
        ->toContain('seller application status is pending')
        ->not->toContain('Support AI is temporarily unavailable');

    Http::assertNothingSent();
});

test('support chat gives guests public account fallback without private data', function () {
    clearOpenRouterConfiguration();
    Http::fake();

    User::factory()->create([
        'name' => 'Private Customer',
        'wallet_balance' => 900,
    ]);

    $response = $this->postJson(route('support.chat.send'), [
        'message' => 'Account',
    ]);

    $response->assertOk();

    expect($response->json('message'))
        ->toContain('sign in')
        ->not->toContain('Private Customer')
        ->not->toContain('900')
        ->not->toContain('Support AI is temporarily unavailable');

    Http::assertNothingSent();
});

test('support chat handles withdrawal help requests when openrouter is not configured', function () {
    clearOpenRouterConfiguration();
    Http::fake();

    $response = $this->postJson(route('support.chat.send'), [
        'message' => 'Cannot withdraw money',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Wallet actions are available after signing in from your account wallet page. If a payment or balance looks wrong, click "Talk to a human" so support can review the transaction.');

    Http::assertNothingSent();
});

test('support chat sends scoped platform context to openrouter', function () {
    config([
        'services.openrouter.api_key' => 'test-openrouter-key',
        'services.openrouter.model' => 'tencent/hy3-preview:free',
    ]);

    $user = User::factory()->create([
        'name' => 'Taylor Bidder',
        'role' => User::ROLE_USER,
        'wallet_balance' => 250.75,
        'held_balance' => 50,
    ]);

    $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
    $auction = Auction::factory()->featured()->create([
        'user_id' => $seller->id,
        'title' => 'Vintage Camera',
        'current_price' => 120,
        'currency' => 'USD',
        'end_time' => now()->addHour(),
    ]);

    Category::query()->create([
        'name' => 'Collectibles',
        'slug' => 'collectibles',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $user->id,
        'type' => WalletTransaction::TYPE_DEPOSIT,
        'amount' => 200,
        'balance_after' => 250.75,
        'description' => 'Stripe top-up',
    ]);

    Bid::factory()->create([
        'user_id' => $user->id,
        'auction_id' => $auction->id,
        'amount' => 120,
    ]);

    Invoice::factory()->create([
        'buyer_id' => $user->id,
        'seller_id' => $seller->id,
        'auction_id' => $auction->id,
        'invoice_number' => 'INV-TEST-00001',
        'total' => 120,
        'currency' => 'USD',
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Your account has wallet and bidding activity available in the dashboard.',
                ],
            ]],
        ]),
    ]);

    RateLimiter::clear('support-chat:'.$user->id);

    $this->actingAs($user)->postJson(route('support.chat.send'), [
        'message' => 'Account',
    ])->assertOk();

    Http::assertSent(function ($request) {
        $messages = $request->data()['messages'] ?? [];
        $system = $messages[0]['content'] ?? '';

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && str_contains($system, 'Access scope: logged-in user private records plus public platform records.')
            && str_contains($system, 'Logged-in user context:')
            && str_contains($system, 'Taylor Bidder')
            && str_contains($system, 'USD 250.75')
            && str_contains($system, 'Vintage Camera')
            && str_contains($system, 'INV-TEST-00001')
            && str_contains($system, 'Collectibles');
    });
});

test('guest openrouter context stays public only', function () {
    config(['services.openrouter.api_key' => 'test-openrouter-key']);

    User::factory()->create([
        'name' => 'Hidden Wallet Owner',
        'wallet_balance' => 777,
    ]);

    Category::query()->create([
        'name' => 'Public Category',
        'slug' => 'public-category',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Please sign in to see account details.',
                ],
            ]],
        ]),
    ]);

    $this->postJson(route('support.chat.send'), [
        'message' => 'Account',
    ])->assertOk();

    Http::assertSent(function ($request) {
        $system = ($request->data()['messages'] ?? [])[0]['content'] ?? '';

        return str_contains($system, 'Access scope: guest public platform records only.')
            && str_contains($system, 'Public Category')
            && ! str_contains($system, 'Logged-in user context:')
            && ! str_contains($system, 'Hidden Wallet Owner')
            && ! str_contains($system, '777');
    });
});

test('support chat does not expose generic processing error when openrouter returns an error payload', function () {
    config(['services.openrouter.api_key' => 'test-openrouter-key']);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'API key not valid.',
            ],
        ], 403),
    ]);

    $response = $this->postJson(route('support.chat.send'), [
        'message' => 'My bid did not go through.',
    ]);

    $response->assertOk();

    expect($response->json('message'))
        ->toContain('For bidding issues')
        ->not->toContain("couldn't process your request");
});

test('support chat fallback handles main support intents without generic unavailable message', function (string $prompt, string $expected) {
    clearOpenRouterConfiguration();
    Http::fake();

    $response = $this->postJson(route('support.chat.send'), [
        'message' => $prompt,
    ]);

    $response->assertOk();

    expect($response->json('message'))
        ->toContain($expected)
        ->not->toContain('Support AI is temporarily unavailable');

    Http::assertNothingSent();
})->with([
    ['Wallet', 'Wallet actions are available'],
    ['Payment', 'For disputes, refunds, returns, or invoices'],
    ['Bid', 'For bidding issues'],
    ['Seller', 'Seller applications are reviewed'],
    ['Account', 'For account help'],
]);
