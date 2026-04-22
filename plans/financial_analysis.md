# Financials, Administration & Analytics — Implementation Plan

> Codebase baseline: Laravel 12, PostgreSQL, Stripe Connect, Redis, Horizon, `WalletService`, `PaymentService`.

---

## Feature 120 — Currency Conversion

### A. Feature Overview
Users select their preferred display currency. Auction prices are stored in USD (base currency) but displayed in the user's selected currency using live exchange rates. Bidding and payment always occur in USD (or the auction's native currency).

**Business goal:** International buyer accessibility; reduces friction for non-USD users.

---

### B. Current State
- `Auction` model has `currency` column (default `USD`); `supported_currencies` in config: `['USD', 'EUR', 'GBP', 'JPY', 'VND']`.
- `WalletTransaction` stores in USD.
- No exchange rate fetching, storage, or display conversion exists.
- `UserPreference` has `timezone` but no `display_currency`.

---

### C. Required Changes

#### 1. Database

```php
// Migration: add display_currency to user_preferences
Schema::table('user_preferences', function (Blueprint $table) {
    $table->string('display_currency', 3)->default('USD')->after('locale');
});

// Migration: create exchange_rates table (cached rates from external API)
Schema::create('exchange_rates', function (Blueprint $table) {
    $table->id();
    $table->string('base_currency', 3)->default('USD');
    $table->string('target_currency', 3);
    $table->decimal('rate', 18, 8); // e.g., 1 USD = 25000.00000000 VND
    $table->timestamp('fetched_at');
    $table->timestamps();

    $table->unique(['base_currency', 'target_currency']);
    $table->index('fetched_at');
});
```

#### 2. Backend Logic

**`app/Services/ExchangeRateService.php`** — new service:

```php
namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    private const CACHE_KEY    = 'exchange_rates:v1';
    private const CACHE_TTL    = 3600; // 1 hour
    private const STALE_HOURS  = 24;  // Use stale DB rates if API down

    public function getRate(string $from, string $to): float
    {
        if ($from === $to) return 1.0;

        $rates = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->loadRates());

        return (float) ($rates["{$from}_{$to}"] ?? 1.0);
    }

    public function convert(float $amount, string $from, string $to): float
    {
        return round($amount * $this->getRate($from, $to), 2);
    }

    /**
     * Fetch fresh rates from external API and persist.
     * Called by scheduler every hour.
     */
    public function refresh(): void
    {
        $apiKey = config('services.exchange_rate.api_key');
        $apiUrl = config('services.exchange_rate.url', 'https://api.exchangeratesapi.io/v1/latest');
        $supported = config('auction.supported_currencies', ['USD', 'EUR', 'GBP', 'JPY', 'VND']);

        try {
            $response = Http::timeout(10)->get($apiUrl, [
                'access_key' => $apiKey,
                'symbols'    => implode(',', $supported),
            ]);

            if (! $response->ok()) {
                throw new \RuntimeException("Exchange rate API returned {$response->status()}");
            }

            $data  = $response->json();
            $rates = $data['rates'] ?? [];
            $base  = strtoupper($data['base'] ?? 'EUR'); // Many free APIs use EUR as base

            // Store rates relative to USD
            $usdRate = $rates['USD'] ?? 1.0;

            foreach ($supported as $currency) {
                if (! isset($rates[$currency]) || $currency === 'USD') continue;

                $rate = round($rates[$currency] / $usdRate, 8);

                ExchangeRate::updateOrCreate(
                    ['base_currency' => 'USD', 'target_currency' => $currency],
                    ['rate' => $rate, 'fetched_at' => now()],
                );
            }

            Cache::forget(self::CACHE_KEY);
            Log::info('ExchangeRateService: rates refreshed', ['currencies' => $supported]);

        } catch (\Throwable $e) {
            Log::error('ExchangeRateService: refresh failed', ['error' => $e->getMessage()]);
            // Do not clear cache — stale rates are better than no rates
        }
    }

    private function loadRates(): array
    {
        // Load from DB (stale-safe fallback)
        return ExchangeRate::where('base_currency', 'USD')
            ->get()
            ->mapWithKeys(fn ($r) => [
                "USD_{$r->target_currency}" => (float) $r->rate
            ])
            ->all();
    }
}
```

**`app/Models/ExchangeRate.php`** — new model (standard Eloquent, matches migration).

**`app/Http/Middleware/SetDisplayCurrency.php`** — new middleware:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetDisplayCurrency
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $currency = $user?->userPreference?->display_currency
            ?? $request->cookie('display_currency')
            ?? 'USD';

        app()->instance('display_currency', strtoupper($currency));
        return $next($request);
    }
}
```

**Blade view helper / global function** — `app/helpers.php`:

```php
if (! function_exists('format_price')) {
    function format_price(float $amountUsd, ?string $currency = null): string
    {
        $currency = $currency ?? app()->make('display_currency', ['default' => 'USD']);

        if ($currency === 'USD') {
            return '$' . number_format($amountUsd, 2);
        }

        $rate      = app(ExchangeRateService::class)->getRate('USD', $currency);
        $converted = $amountUsd * $rate;

        $symbols = ['EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'VND' => '₫'];
        $symbol  = $symbols[$currency] ?? $currency . ' ';

        $decimals = in_array($currency, ['JPY', 'VND']) ? 0 : 2;
        return $symbol . number_format($converted, $decimals);
    }
}
```

Register in `composer.json` `autoload.files` or as a service provider.

**`routes/console.php`** — add scheduler:

```php
Schedule::call(fn () => app(ExchangeRateService::class)->refresh())
    ->hourly()
    ->name('refresh-exchange-rates')
    ->withoutOverlapping();
```

**`config/services.php`** — add:

```php
'exchange_rate' => [
    'api_key' => env('EXCHANGE_RATE_API_KEY'),
    'url'     => env('EXCHANGE_RATE_API_URL', 'https://api.exchangeratesapi.io/v1/latest'),
],
```

**Currency preference endpoint:**

```php
// Unauthenticated currency switch (cookie-based)
Route::post('/preferences/currency', function (Request $request) {
    $request->validate(['currency' => ['required', Rule::in(config('auction.supported_currencies'))]]);
    return back()->withCookie(cookie('display_currency', $request->input('currency'), 60 * 24 * 365));
})->name('preferences.currency');
```

#### 3. API / Routes

```php
Route::post('/preferences/currency', ...)->name('preferences.currency');
Route::get('/api/exchange-rates', function () {
    return response()->json(
        ExchangeRate::where('base_currency', 'USD')->get(['target_currency', 'rate', 'fetched_at'])
    );
})->name('api.exchange-rates');
```

#### 4. Frontend (contract)
- Currency selector in navbar (dropdown with flag icons).
- Replace all `$X.XX` displays with `format_price()` helper in Blade views.
- Bid form: show "Minimum bid: X.XX [CURRENCY] (equivalent of $Y.YY USD)".
- **Important:** All bid amounts submitted in USD — the form converts display only.

---

### D. Dependencies & Risks
- **API dependency:** If the exchange rate API is down, stale DB rates are used (up to 24 hours). Acceptable degradation.
- **Bid amounts are always USD:** Never store or bid in a converted currency — conversion is display-only.
- **VAT/tax:** Currency display does not include tax — handle separately (not in scope).
- **Free API limitations:** `exchangeratesapi.io` free tier uses EUR as base; paid tier uses USD. The `refresh()` method normalises to USD base regardless.

---

### E. Implementation Steps
1. Choose and sign up for exchange rate API; add key to `.env`.
2. Migration: add `display_currency` to `user_preferences`; create `exchange_rates`.
3. Create `ExchangeRate` model.
4. Create `ExchangeRateService` with `getRate()`, `convert()`, `refresh()`.
5. Create `SetDisplayCurrency` middleware; register in web group.
6. Create `format_price()` global helper; register in composer autoload.
7. Add hourly scheduler for rate refresh.
8. Register currency preference route + API endpoint.
9. Replace price displays in Blade views with `format_price()`.
10. Write tests: rate fetching, conversion, stale fallback, middleware.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 124 — Payout Schedule (Weekly, Bi-weekly, Monthly)

### A. Feature Overview
Sellers choose a payout schedule (instant / weekly / bi-weekly / monthly). Earned seller credits accumulate in an internal "pending payout" balance, then are transferred to Stripe Connect on the chosen schedule.

**Business goal:** Predictable cash flow for sellers; reduces Stripe transfer costs (batch transfers); standard for marketplace payouts (eBay, Etsy model).

---

### B. Current State
- `PaymentService::captureWinnerPayment()` immediately calls `WalletService::creditSeller()` after capture.
- `WithdrawalController` + Stripe Transfer API handles manual withdrawals.
- No scheduled transfer concept exists.

---

### C. Required Changes

#### 1. Database

```php
// Payout schedule preference on sellers
Schema::table('users', function (Blueprint $table) {
    $table->string('payout_schedule', 20)->default('instant')->after('stripe_connect_onboarded');
    // Values: 'instant', 'weekly', 'biweekly', 'monthly'
    $table->string('payout_schedule_day', 10)->nullable()->after('payout_schedule');
    // For weekly: 'monday'-'sunday'; for monthly: '1'-'28'
    $table->decimal('pending_payout_balance', 15, 2)->default(0.00)->after('payout_schedule_day');
    // Funds earned but not yet transferred to bank
});

Schema::create('payout_batches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->string('status', 20)->default('pending');
    // 'pending', 'processing', 'completed', 'failed'
    $table->string('stripe_transfer_id')->nullable();
    $table->timestamp('scheduled_for');
    $table->timestamp('processed_at')->nullable();
    $table->text('failure_reason')->nullable();
    $table->timestamps();

    $table->index(['status', 'scheduled_for']);
    $table->index(['user_id', 'status']);
});
```

#### 2. Backend Logic

**`app/Services/PaymentService.php`** — modify seller credit logic:

```php
public function captureWinnerPayment(Auction $auction): Invoice
{
    // ... existing escrow capture ...

    $seller = User::findOrFail($auction->user_id);

    if ($seller->payout_schedule === 'instant') {
        // Existing behaviour: immediately credit wallet
        $this->walletService->creditSeller($seller, $sellerAmount, ...);
    } else {
        // New: credit to pending_payout_balance instead
        DB::transaction(function () use ($seller, $sellerAmount, $auction) {
            $seller->increment('pending_payout_balance', $sellerAmount);
            // Still record a wallet transaction for audit trail, using new type
            WalletTransaction::create([
                'user_id'        => $seller->id,
                'type'           => 'payout_pending',
                'amount'         => $sellerAmount,
                'balance_after'  => $seller->wallet_balance, // wallet_balance unchanged
                'description'    => "Auction #{$auction->id} earnings pending scheduled payout",
            ]);
        });
    }
}
```

**`WalletTransaction` TYPE constants** — add `TYPE_PAYOUT_PENDING = 'payout_pending'`.

**`app/Services/PayoutScheduleService.php`** — new service:

```php
namespace App\Services;

use App\Models\PayoutBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutScheduleService
{
    public function __construct(protected WalletService $walletService) {}

    /**
     * Process all due payout batches.
     * Called by scheduler (daily).
     */
    public function processDue(): void
    {
        $dueBatches = PayoutBatch::where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->with('user')
            ->get();

        foreach ($dueBatches as $batch) {
            $this->processBatch($batch);
        }
    }

    /**
     * Create a payout batch for a seller based on their schedule.
     * Called when seller's pending_payout_balance > 0 and schedule day matches.
     */
    public function createScheduledBatch(User $seller): ?PayoutBatch
    {
        if ($seller->pending_payout_balance <= 0) return null;

        $scheduledFor = $this->nextPayoutDate($seller);

        return DB::transaction(function () use ($seller, $scheduledFor) {
            $amount = (float) $seller->pending_payout_balance;

            $batch = PayoutBatch::create([
                'user_id'       => $seller->id,
                'amount'        => $amount,
                'status'        => 'pending',
                'scheduled_for' => $scheduledFor,
            ]);

            $seller->decrement('pending_payout_balance', $amount);

            Log::info('PayoutScheduleService: batch created', [
                'batch_id'  => $batch->id,
                'seller_id' => $seller->id,
                'amount'    => $amount,
            ]);

            return $batch;
        });
    }

    private function processBatch(PayoutBatch $batch): void
    {
        $batch->update(['status' => 'processing']);

        try {
            $user = $batch->user;

            if (! $user->hasConnectedBank()) {
                throw new \DomainException('No connected bank account.');
            }

            // Stripe transfer
            $transfer = \Stripe\Transfer::create([
                'amount'      => (int) round($batch->amount * 100),
                'currency'    => 'usd',
                'destination' => $user->stripe_connect_account_id,
                'description' => "Scheduled payout batch #{$batch->id}",
                'metadata'    => ['batch_id' => $batch->id, 'user_id' => $user->id],
            ]);

            // Credit wallet and record transaction
            $this->walletService->creditSeller(
                $user, $batch->amount, "Scheduled payout batch #{$batch->id}"
            );

            $batch->update([
                'status'              => 'completed',
                'stripe_transfer_id'  => $transfer->id,
                'processed_at'        => now(),
            ]);

            $user->notify(new \App\Notifications\PayoutBatchProcessedNotification($batch));

        } catch (\Throwable $e) {
            $batch->update([
                'status'         => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
            Log::error('PayoutScheduleService: batch failed', [
                'batch_id' => $batch->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function nextPayoutDate(User $seller): \Carbon\Carbon
    {
        $now = now();
        return match ($seller->payout_schedule) {
            'weekly'   => $now->next($seller->payout_schedule_day ?? 'monday'),
            'biweekly' => $now->next($seller->payout_schedule_day ?? 'monday')->addWeeks(1),
            'monthly'  => $now->startOfMonth()->addMonth()->setDay((int) ($seller->payout_schedule_day ?? 1)),
            default    => $now, // instant — processed immediately
        };
    }
}
```

**Scheduler** — `routes/console.php`:

```php
// Create payout batches daily for sellers with pending balances
Schedule::call(function () {
    User::where('role', 'seller')
        ->where('pending_payout_balance', '>', 0)
        ->where('payout_schedule', '!=', 'instant')
        ->each(fn ($seller) => app(PayoutScheduleService::class)->createScheduledBatch($seller));
})->daily()->at('02:00')->name('create-payout-batches');

// Process due batches
Schedule::call(fn () => app(PayoutScheduleService::class)->processDue())
    ->hourly()->name('process-due-payouts');
```

**Seller settings controller** — add payout schedule update:

```php
$validated = $request->validate([
    'payout_schedule'     => ['required', Rule::in(['instant', 'weekly', 'biweekly', 'monthly'])],
    'payout_schedule_day' => ['nullable', 'string'],
]);
$request->user()->update($validated);
```

#### 3. API / Routes

```php
Route::patch('/seller/payout-schedule', [SellerSettingsController::class, 'updatePayoutSchedule'])
    ->name('seller.payout-schedule.update');

Route::get('/seller/payout-batches', [SellerPayoutController::class, 'index'])
    ->name('seller.payouts.index');
```

---

### D. Dependencies & Risks
- **Stripe webhook `payout.paid`** — existing `handlePayoutPaid()` in `StripeWebhookController` deducts `wallet_balance`. This continues to work because the wallet credit happens in `processBatch()`, not when the Stripe payout actually hits the bank.
- **Tax withholding:** Some jurisdictions require withholding on scheduled payouts — consult legal before enabling for international sellers.
- **Minimum payout amount:** Add a config `auction.payout.minimum_amount` (e.g., $10) to avoid micro-transfers.

---

### E. Implementation Steps
1. Migrations: add payout schedule columns to `users`; create `payout_batches`.
2. Update `User` model and `WalletTransaction` type constants.
3. Modify `PaymentService::captureWinnerPayment()` for schedule branching.
4. Create `PayoutScheduleService`.
5. Create `PayoutBatchProcessedNotification`.
6. Add scheduler entries.
7. Add payout schedule update endpoint.
8. Write tests: instant payout path unchanged, batch creation, batch processing, failed transfer handling.

---

### F. Complexity & Priority
- **Complexity:** High
- **Priority:** Growth

---

## Feature 138 — Live Chat Support Widget (AI API Support)

### A. Feature Overview
Embed a support chat widget (bottom-right corner) that lets users ask questions. An AI Gemini API via the in-app API provides first-line support answers. Unresolved conversations escalate to human admin.

**Business goal:** 24/7 support availability; reduced support ticket volume; AI handles 70%+ of common questions automatically.

---

### B. Current State
- `Conversation` model exists but is buyer↔seller only.
- No support ticket / chat system for user↔platform communication.
- The platform is built with Reverb WebSockets — can support real-time chat.

---

### C. Required Changes

#### 1. Database

```php
Schema::create('support_conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    // NULL = anonymous pre-login chat
    $table->string('status', 20)->default('open'); // open, ai_handled, escalated, closed
    $table->string('channel', 20)->default('widget'); // widget, email
    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('last_message_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'assigned_to']);
});

Schema::create('support_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
    $table->string('role', 10); // 'user', 'assistant', 'admin'
    $table->text('body');
    $table->boolean('is_ai')->default(false);
    $table->timestamps();

    $table->index(['conversation_id', 'created_at']);
});
```

#### 2. Backend Logic

**`app/Http/Controllers/SupportChatController.php`** — new controller:

```php
namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupportChatController extends Controller
{
    /**
     * Start or continue a support conversation.
     * Sends user message to Gemini API and returns AI response.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'         => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', 'exists:support_conversations,id'],
        ]);

        $user = $request->user();

        // Get or create conversation
        if ($validated['conversation_id']) {
            $conversation = SupportConversation::findOrFail($validated['conversation_id']);
            abort_unless(
                $conversation->user_id === $user?->id || $conversation->user_id === null,
                403
            );
        } else {
            $conversation = SupportConversation::create([
                'user_id' => $user?->id,
                'status'  => 'open',
            ]);
        }

        // Store user message
        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'body'            => $validated['message'],
            'is_ai'           => false,
        ]);

        // Build conversation history for Gemini
        $history = SupportMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'role'    => $m->role === 'admin' ? 'assistant' : $m->role,
                'content' => $m->body,
            ])
            ->all();

        // Call Gemini API
        $aiResponse = $this->askGemini($history, $user);

        // Store AI response
        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'body'            => $aiResponse,
            'is_ai'           => true,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'message'         => $aiResponse,
            'is_ai'           => true,
            'can_escalate'    => true,
        ]);
    }

    /**
     * User requests human support.
     */
    public function escalate(Request $request, SupportConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()?->id, 403);

        $conversation->update(['status' => 'escalated']);

        // Notify admins/moderators
        $admins = \App\Models\User::whereIn('role', ['admin', 'moderator'])->get();
        $admins->each(fn ($a) => $a->notify(new \App\Notifications\SupportEscalationNotification($conversation)));

        return response()->json(['message' => 'Connected to support team. A member will reply shortly.']);
    }

    private function askGemini(array $history, $user): string
    {
        $systemPrompt = $this->buildSystemPrompt($user);

        // Convert history → Gemini format
        $contents = [];

        // Add system prompt as first message
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $systemPrompt]],
        ];

        foreach ($history as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        try {
            $response = Http::post(
                'https://generativelanguage.googleapis.com/v1/models/gemini-3-flash:generateContent?key=' . config('services.gemini.api_key'),
                [
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => 500,
                        'temperature' => 0.7,
                    ],
                ]
            );

            $data = $response->json();

            return $data['candidates'][0]['content']['parts'][0]['text']
                ?? "I'm sorry, I couldn't process your request. Please try again.";

        } catch (\Throwable $e) {
            Log::error('SupportChatController: Gemini API error', [
                'error' => $e->getMessage()
            ]);

            return 'I\'m temporarily unavailable. Please click "Talk to a human" to connect with our support team.';
        }
    }

    private function buildSystemPrompt($user): string
    {
        $context = $user
            ? "The user is logged in as {$user->name} (role: {$user->role}). "
            : "The user is not logged in. ";

        return <<<PROMPT
You are a helpful customer support assistant for an online auction platform.
{$context}
Answer questions about: bidding, auction rules, payment, wallets, seller applications, disputes, and account issues.
Keep responses concise (2-3 sentences max). If you cannot confidently answer or if the user is frustrated, suggest they click "Talk to a human" to escalate.
Do not make up platform-specific URLs, prices, or policies you are not certain about.
PROMPT;
    }
}
```

**Admin support inbox:**

```php
// app/Http/Controllers/Admin/SupportInboxController.php
// index(): list escalated/open conversations
// reply(): send admin message to conversation
// close(): mark conversation closed
```

#### 3. API / Routes

```php
// Public (no auth required for widget)
Route::post('/support/chat',                         [SupportChatController::class, 'send'])->name('support.chat.send');
Route::post('/support/chat/{conversation}/escalate', [SupportChatController::class, 'escalate'])->name('support.chat.escalate');

// Admin
Route::prefix('/admin/support')->name('admin.support.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/',                    [SupportInboxController::class, 'index'])->name('index');
    Route::get('/{conversation}',      [SupportInboxController::class, 'show'])->name('show');
    Route::post('/{conversation}/reply', [SupportInboxController::class, 'reply'])->name('reply');
    Route::post('/{conversation}/close', [SupportInboxController::class, 'close'])->name('close');
});
```

**`config/services.php`** — add:

```php
'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
],
```

#### 4. Frontend (contract)
- Floating chat bubble (bottom-right) across all pages.
- Chat window: conversation history, user input, "Send" button.
- After 2 AI messages: offer "Talk to a human" escalation button.
- Admin: support inbox page showing open/escalated conversations with reply capability.

---

### D. Dependencies & Risks
- **API cost:** Each support message makes a Gemini API call. Implement rate limiting: max 10 messages per user per hour.
- **Privacy:** Support conversations may contain sensitive info. Store in DB (already on private server), don't log to external services.
- **AI accuracy:** Gemini does not know platform-specific data (current prices, specific user accounts). System prompt scopes responses to general help only.

---

### E. Implementation Steps
1. Migrations: create `support_conversations`, `support_messages`.
2. Create `SupportConversation`, `SupportMessage` models.
3. Create `SupportChatController`.
4. Create admin `SupportInboxController`.
5. Add Anthropic API key to config/services.php.
6. Create `SupportEscalationNotification`.
7. Register routes.
8. Add rate limiting to `send()` endpoint.
9. Write tests.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 162 — Admin Scheduled Maintenance Mode

### A. Feature Overview
Admins schedule maintenance windows in advance. Users see a countdown banner before maintenance and a maintenance page during downtime. The system blocks non-admin traffic during maintenance while preserving admin access.

**Business goal:** Graceful handling of database migrations and updates; reduces user confusion during downtime.

---

### B. Current State
- Laravel has built-in maintenance mode (`php artisan down`) but it's manual.
- No scheduled maintenance concept or admin UI to control it.
- No countdown banner feature.

---

### C. Required Changes

#### 1. Database

```php
Schema::create('maintenance_windows', function (Blueprint $table) {
    $table->id();
    $table->timestamp('scheduled_start');
    $table->timestamp('scheduled_end');
    $table->string('message', 500)->default('Scheduled maintenance. Back soon.');
    $table->string('status', 20)->default('scheduled'); // scheduled, active, completed, cancelled
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();

    $table->index(['status', 'scheduled_start']);
});
```

#### 2. Backend Logic

**`app/Services/MaintenanceWindowService.php`** — new service:

```php
namespace App\Services;

use App\Models\MaintenanceWindow;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class MaintenanceWindowService
{
    private const CACHE_KEY   = 'maintenance:upcoming';
    private const CACHE_TTL   = 60;

    public function getUpcoming(): ?MaintenanceWindow
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () =>
            MaintenanceWindow::where('status', 'scheduled')
                ->where('scheduled_start', '<=', now()->addHours(24))
                ->orderBy('scheduled_start')
                ->first()
        );
    }

    public function activateDue(): void
    {
        $window = MaintenanceWindow::where('status', 'scheduled')
            ->where('scheduled_start', '<=', now())
            ->first();

        if (! $window) return;

        $window->update(['status' => 'active']);
        Cache::forget(self::CACHE_KEY);

        // Put Laravel into maintenance mode with secret bypass token for admins
        Artisan::call('down', [
            '--secret' => config('app.maintenance_bypass_token', 'admin-bypass-' . md5(config('app.key'))),
            '--render' => 'errors.maintenance',
            '--retry'  => 60,
        ]);
    }

    public function deactivateExpired(): void
    {
        $window = MaintenanceWindow::where('status', 'active')
            ->where('scheduled_end', '<=', now())
            ->first();

        if (! $window) return;

        $window->update(['status' => 'completed']);
        Cache::forget(self::CACHE_KEY);

        Artisan::call('up');
    }
}
```

**Scheduler** — `routes/console.php`:

```php
Schedule::call(fn () => app(MaintenanceWindowService::class)->activateDue())
    ->everyMinute()
    ->name('activate-maintenance-window')
    ->withoutOverlapping();

Schedule::call(fn () => app(MaintenanceWindowService::class)->deactivateExpired())
    ->everyMinute()
    ->name('deactivate-maintenance-window')
    ->withoutOverlapping();
```

**Admin controller:**

```php
// app/Http/Controllers/Admin/MaintenanceController.php
// index(), store(), update(), destroy(), cancel()
// Standard CRUD on MaintenanceWindow model
```

**Maintenance announcement middleware** — adds banner data to all responses:

```php
// app/Http/Middleware/MaintenanceAnnouncement.php
public function handle(Request $request, Closure $next): Response
{
    $upcoming = app(MaintenanceWindowService::class)->getUpcoming();
    if ($upcoming) {
        view()->share('maintenance_window', $upcoming);
    }
    return $next($request);
}
```

#### 3. Routes

```php
Route::prefix('/admin/maintenance')->name('admin.maintenance.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',          [MaintenanceController::class, 'index'])->name('index');
    Route::post('/',         [MaintenanceController::class, 'store'])->name('store');
    Route::patch('/{window}',[MaintenanceController::class, 'update'])->name('update');
    Route::post('/{window}/cancel', [MaintenanceController::class, 'cancel'])->name('cancel');
});
```

#### 4. Frontend (contract)
- **All pages (when maintenance upcoming within 2 hours):** Banner at top: "⚠ Scheduled maintenance on [DATE] from [START] to [END]. Please save your work."
- **Maintenance page (`errors/maintenance.blade.php`):** Full-screen "We'll be right back" page with countdown timer.
- **Admin bypass:** Admins can access the site during maintenance using the secret URL (`?secret=admin-bypass-...`).

---

### E. Implementation Steps
1. Migration: create `maintenance_windows`.
2. Create `MaintenanceWindow` model.
3. Create `MaintenanceWindowService`.
4. Create `MaintenanceAnnouncement` middleware.
5. Create `errors/maintenance.blade.php` view.
6. Create admin `MaintenanceController`.
7. Register routes and scheduler.
8. Write tests.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Scaling

---

## Features 169–172 — Analytics Reports

### A. Feature Overview
Four interconnected analytics features:
- **169:** Category performance reports (auction counts, sell-through rates, avg prices per category).
- **170:** Peak bidding time analysis (bid volume by hour/day, recommends optimal listing time).
- **171:** Seller leaderboard (top sellers by revenue, listing count, feedback score).
- **172:** Buyer activity reports (admin view of buyer spending, bid frequency, win rate).

**Business goal:** Data-driven decisions; seller coaching; fraud detection; marketplace health monitoring.

---

### B. Current State
- `Bid`, `Auction`, `Invoice`, `AuctionSnapshot` tables have all required raw data.
- `DashboardController::bidThroughput()` does hourly bid aggregation (170 starting point).
- `AnalyticsController` in seller namespace has view/bid data per seller.
- No cross-seller or category-level aggregation exists.

---

### C. Required Changes

#### 1. Database — Pre-aggregated report tables (for performance)

```php
// Nightly materialized analytics snapshots
Schema::create('analytics_category_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->date('report_date');
    $table->unsignedInteger('total_auctions')->default(0);
    $table->unsignedInteger('completed_auctions')->default(0);
    $table->unsignedInteger('cancelled_auctions')->default(0);
    $table->decimal('sell_through_rate', 5, 4)->default(0); // completed / (completed + ended_no_winner)
    $table->decimal('avg_final_price', 15, 2)->default(0);
    $table->decimal('avg_starting_price', 15, 2)->default(0);
    $table->decimal('price_appreciation_pct', 8, 4)->default(0); // (final - starting) / starting
    $table->unsignedInteger('total_bids')->default(0);
    $table->unsignedInteger('unique_bidders')->default(0);
    $table->timestamps();

    $table->unique(['category_id', 'report_date']);
});

Schema::create('analytics_hourly_bid_volume', function (Blueprint $table) {
    $table->id();
    $table->date('report_date');
    $table->unsignedTinyInteger('hour_of_day'); // 0-23
    $table->string('day_of_week', 10);          // 'monday', 'tuesday', etc.
    $table->unsignedInteger('bid_count')->default(0);
    $table->unsignedInteger('unique_bidders')->default(0);
    $table->unsignedInteger('unique_auctions')->default(0);
    $table->timestamps();

    $table->unique(['report_date', 'hour_of_day']);
    $table->index(['day_of_week', 'hour_of_day']);
});

Schema::create('analytics_seller_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('report_date');
    $table->unsignedInteger('active_listings')->default(0);
    $table->unsignedInteger('completed_sales')->default(0);
    $table->decimal('gross_revenue', 15, 2)->default(0);
    $table->decimal('avg_sale_price', 15, 2)->default(0);
    $table->decimal('avg_rating', 4, 2)->default(0);
    $table->unsignedInteger('total_bids_received')->default(0);
    $table->timestamps();

    $table->unique(['user_id', 'report_date']);
    $table->index('report_date');
});
```

#### 2. Backend Logic

**`app/Jobs/GenerateAnalyticsSnapshot.php`** — nightly job:

```php
namespace App\Jobs;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\AnalyticsCategorySnapshot;
use App\Models\AnalyticsHourlyBidVolume;
use App\Models\AnalyticsSellerSnapshot;
use App\Models\Category;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAnalyticsSnapshot implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 600; // 10 minutes

    public function handle(): void
    {
        $reportDate = now()->subDay()->toDateString();

        $this->generateCategorySnapshots($reportDate);
        $this->generateHourlyBidVolume($reportDate);
        $this->generateSellerSnapshots($reportDate);

        Log::info("GenerateAnalyticsSnapshot: completed for {$reportDate}");
    }

    private function generateCategorySnapshots(string $date): void
    {
        Category::all()->each(function (Category $category) use ($date) {
            $auctionIds = Auction::whereHas('categories', fn ($q) =>
                $q->where('categories.id', $category->id)
            )->pluck('id');

            if ($auctionIds->isEmpty()) return;

            $completed = Auction::whereIn('id', $auctionIds)
                ->where('status', 'completed')
                ->whereDate('closed_at', $date);

            $stats = $completed->selectRaw('
                COUNT(*) as total,
                AVG(winning_bid_amount) as avg_final,
                AVG(starting_price) as avg_start,
                AVG(CASE WHEN starting_price > 0 THEN (winning_bid_amount - starting_price) / starting_price ELSE 0 END) as appreciation
            ')->first();

            AnalyticsCategorySnapshot::updateOrCreate(
                ['category_id' => $category->id, 'report_date' => $date],
                [
                    'completed_auctions'      => $stats->total ?? 0,
                    'avg_final_price'         => round((float) ($stats->avg_final ?? 0), 2),
                    'avg_starting_price'      => round((float) ($stats->avg_start ?? 0), 2),
                    'price_appreciation_pct'  => round((float) ($stats->appreciation ?? 0) * 100, 4),
                    'total_bids'              => Bid::whereIn('auction_id', $auctionIds)->whereDate('created_at', $date)->count(),
                ]
            );
        });
    }

    private function generateHourlyBidVolume(string $date): void
    {
        $hourlyData = Bid::whereDate('created_at', $date)
            ->selectRaw("
                EXTRACT(HOUR FROM created_at)::int AS hour,
                TO_CHAR(created_at, 'Day') AS day_name,
                COUNT(*) AS bid_count,
                COUNT(DISTINCT user_id) AS unique_bidders,
                COUNT(DISTINCT auction_id) AS unique_auctions
            ")
            ->groupBy(DB::raw("EXTRACT(HOUR FROM created_at), TO_CHAR(created_at, 'Day')"))
            ->get();

        foreach ($hourlyData as $row) {
            AnalyticsHourlyBidVolume::updateOrCreate(
                ['report_date' => $date, 'hour_of_day' => $row->hour],
                [
                    'day_of_week'     => strtolower(trim($row->day_name)),
                    'bid_count'       => $row->bid_count,
                    'unique_bidders'  => $row->unique_bidders,
                    'unique_auctions' => $row->unique_auctions,
                ]
            );
        }
    }

    private function generateSellerSnapshots(string $date): void
    {
        User::where('role', 'seller')->each(function (User $seller) use ($date) {
            $completed = Auction::where('user_id', $seller->id)
                ->where('status', 'completed')
                ->whereDate('closed_at', $date);

            AnalyticsSellerSnapshot::updateOrCreate(
                ['user_id' => $seller->id, 'report_date' => $date],
                [
                    'completed_sales'     => (clone $completed)->count(),
                    'gross_revenue'       => (float) (clone $completed)->sum('winning_bid_amount'),
                    'avg_sale_price'      => (float) (clone $completed)->avg('winning_bid_amount') ?? 0,
                    'total_bids_received' => Bid::whereHas('auction', fn ($q) => $q->where('user_id', $seller->id))
                                              ->whereDate('created_at', $date)->count(),
                ]
            );
        });
    }
}
```

**Admin analytics controllers:**

**`app/Http/Controllers/Admin/CategoryAnalyticsController.php`** (Feature 169):

```php
public function index(Request $request): JsonResponse
{
    $days = $request->input('days', 30);
    $from = now()->subDays($days)->toDateString();

    $data = AnalyticsCategorySnapshot::where('report_date', '>=', $from)
        ->with('category:id,name,slug')
        ->selectRaw('
            category_id,
            SUM(completed_auctions) as total_sales,
            AVG(avg_final_price) as avg_price,
            AVG(price_appreciation_pct) as avg_appreciation,
            SUM(total_bids) as total_bids
        ')
        ->groupBy('category_id')
        ->orderByDesc('total_sales')
        ->paginate(20);

    return response()->json(['data' => $data]);
}
```

**`app/Http/Controllers/Admin/BidTimingController.php`** (Feature 170):

```php
public function heatmap(Request $request): JsonResponse
{
    $days = $request->input('days', 30);
    $from = now()->subDays($days)->toDateString();

    $data = AnalyticsHourlyBidVolume::where('report_date', '>=', $from)
        ->selectRaw('
            hour_of_day, day_of_week,
            SUM(bid_count) as total_bids,
            AVG(bid_count) as avg_bids
        ')
        ->groupBy('hour_of_day', 'day_of_week')
        ->orderBy('hour_of_day')
        ->get();

    // Peak time recommendation
    $peak = $data->sortByDesc('avg_bids')->first();

    return response()->json([
        'heatmap'       => $data,
        'peak_hour'     => $peak?->hour_of_day,
        'peak_day'      => $peak?->day_of_week,
        'recommendation'=> $peak
            ? "Best time to list: {$peak->day_of_week} at {$peak->hour_of_day}:00 UTC"
            : 'Insufficient data',
    ]);
}
```

**`app/Http/Controllers/Admin/SellerLeaderboardController.php`** (Feature 171):

```php
public function index(Request $request): JsonResponse
{
    $period = $request->input('period', 30); // days
    $from   = now()->subDays($period)->toDateString();

    $leaderboard = AnalyticsSellerSnapshot::where('report_date', '>=', $from)
        ->with('user:id,name,seller_slug,seller_verified_at')
        ->selectRaw('user_id, SUM(gross_revenue) as total_revenue, SUM(completed_sales) as total_sales, AVG(avg_rating) as avg_rating')
        ->groupBy('user_id')
        ->orderByDesc('total_revenue')
        ->limit(50)
        ->get();

    return response()->json(['data' => $leaderboard]);
}
```

**`app/Http/Controllers/Admin/BuyerAnalyticsController.php`** (Feature 172):

```php
// Queries Bid and Auction tables directly — no pre-aggregated table needed for admin use
public function report(Request $request): JsonResponse
{
    $userId = $request->integer('user_id');
    $days   = $request->input('days', 30);
    $from   = now()->subDays($days);

    $user  = User::findOrFail($userId);
    $bids  = $user->bids()->where('created_at', '>=', $from);
    $won   = $user->wonAuctions()->where('closed_at', '>=', $from);

    return response()->json([
        'user_id'        => $userId,
        'name'           => $user->name,
        'total_bids'     => (clone $bids)->count(),
        'auctions_bid_on'=> (clone $bids)->distinct('auction_id')->count('auction_id'),
        'auctions_won'   => $won->count(),
        'total_spent'    => (float) $won->sum('winning_bid_amount'),
        'avg_bid_amount' => (float) (clone $bids)->avg('amount'),
        'win_rate_pct'   => $bids->distinct('auction_id')->count() > 0
            ? round(($won->count() / $bids->distinct('auction_id')->count()) * 100, 1)
            : 0,
        'wallet_balance' => (float) $user->wallet_balance,
    ]);
}
```

**Scheduler** — `routes/console.php`:

```php
Schedule::job(new GenerateAnalyticsSnapshot)
    ->dailyAt('01:00')
    ->name('generate-analytics-snapshot')
    ->withoutOverlapping();
```

#### 3. API / Routes

```php
// Admin analytics routes
Route::prefix('/admin/analytics')->name('admin.analytics.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/categories',  [CategoryAnalyticsController::class, 'index'])->name('categories');
    Route::get('/bid-timing',  [BidTimingController::class, 'heatmap'])->name('bid-timing');
    Route::get('/leaderboard', [SellerLeaderboardController::class, 'index'])->name('leaderboard');
    Route::get('/buyers',      [BuyerAnalyticsController::class, 'index'])->name('buyers');
    Route::get('/buyers/{user}', [BuyerAnalyticsController::class, 'report'])->name('buyers.report');
});
```

#### 4. Frontend (contract)
- **Feature 169:** Sortable table of categories with sell-through %, avg price, trend sparkline.
- **Feature 170:** 7×24 heatmap grid (day × hour), colour intensity = bid volume. "Best listing time" recommendation card.
- **Feature 171:** Ranked leaderboard table with revenue, sales count, rating. Period filter (7/30/90 days).
- **Feature 172:** Admin user profile page adds "Buyer Analytics" tab with metrics.

---

### D. Dependencies & Risks
- **Nightly job duration:** With thousands of categories and sellers, `GenerateAnalyticsSnapshot` may take several minutes. `$timeout = 600` and `withoutOverlapping()` prevent issues.
- **PostgreSQL-specific SQL:** `EXTRACT(HOUR FROM ...)`, `TO_CHAR()` are Postgres-specific. Feature 92 already uses Postgres — consistent.
- **Real-time vs batch:** These are batch analytics (nightly). For real-time bid counts, use `DashboardController::liveMetrics()` (already exists).

---

### E. Implementation Steps (All four features — shared job)
1. Migrations: create `analytics_category_snapshots`, `analytics_hourly_bid_volume`, `analytics_seller_snapshots`.
2. Create corresponding Eloquent models.
3. Create `GenerateAnalyticsSnapshot` job with three private methods.
4. Register nightly scheduler.
5. Create `CategoryAnalyticsController`, `BidTimingController`, `SellerLeaderboardController`, `BuyerAnalyticsController`.
6. Register all routes.
7. Write integration tests validating snapshot data matches expected aggregates.

---

### F. Complexity & Priority
- **Complexity:** Medium (each controller is simple; the job is the complex part)
- **Priority:** Growth (169, 171), Scaling (170, 172)

---

## Shared Components

| Component | Used By |
|-----------|---------|
| `WalletService` | Currency (120), Payout Schedule (124) |
| `Stripe\Transfer` | Payout Schedule (124), Withdrawal (existing) |
| `AuditLog` | Maintenance (162), all admin writes |
| Scheduler | All features in this section |
| `Http::post()` (internal API) | Support Chat (138) |
| Aggregation job pattern | Analytics 169-172 |

## Quick Wins
- **Feature 97** featured categories + **Feature 169** category analytics can share category-level queries.
- **Feature 170** heatmap builds on `DashboardController::bidThroughput()` — mostly a new pre-aggregation layer.

## Architectural Note: Analytics Pipeline
As data volume grows, consider replacing the nightly `GenerateAnalyticsSnapshot` job with PostgreSQL materialized views refreshed via `CONCURRENTLY`. This avoids duplicating data in separate tables and simplifies the data model:

```sql
CREATE MATERIALIZED VIEW mv_category_performance AS
SELECT
    c.id AS category_id,
    COUNT(a.id) AS total_auctions,
    AVG(a.winning_bid_amount) AS avg_final_price,
    ...
FROM categories c
JOIN auction_category ac ON c.id = ac.category_id
JOIN auctions a ON a.id = ac.auction_id
WHERE a.status = 'completed'
GROUP BY c.id;

-- Refresh nightly:
REFRESH MATERIALIZED VIEW CONCURRENTLY mv_category_performance;
```

This is the preferred long-term architecture. The Eloquent-based approach above is the pragmatic short-term solution that fits the current codebase patterns.