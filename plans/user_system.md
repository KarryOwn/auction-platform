# User System — Implementation Plan

> Codebase baseline: Laravel 12, PostgreSQL, `User` model (Eloquent), Sanctum, Horizon queues, database notifications, Reverb WebSockets.

---

## Feature 43 — User Referral Program

### A. Feature Overview
Each user receives a unique referral code. When a new user registers using that code, both the referrer and the new user receive a wallet credit (configurable amount). Track referral chains and prevent abuse.

**Business goal:** Low-cost user acquisition; word-of-mouth growth loop.

---

### B. Current State
- `User` model — no referral code column.
- `WalletService::deposit()` — exists and can credit wallet.
- `RegisteredUserController::store()` — creates user, no referral hook.
- `AuditLog::record()` — available for tracking.

---

### C. Required Changes

#### 1. Database

```php
// Migration: add_referral_to_users_table
Schema::table('users', function (Blueprint $table) {
    $table->string('referral_code', 12)->nullable()->unique()->after('seller_slug');
    $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')
          ->constrained('users')->nullOnDelete();
    $table->index('referral_code');
});

// Migration: create_referral_rewards_table
Schema::create('referral_rewards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('referee_id')->constrained('users')->cascadeOnDelete();
    $table->decimal('referrer_reward', 10, 2);
    $table->decimal('referee_reward', 10, 2);
    $table->string('status', 20)->default('pending');
    // 'pending' → triggered on registration; 'credited' → after first bid/purchase
    $table->timestamp('credited_at')->nullable();
    $table->timestamps();

    $table->unique('referee_id'); // one referral per user
    $table->index(['referrer_id', 'status']);
});
```

#### 2. Backend Logic

**`app/Models/User.php`** — add:

```php
'referral_code', 'referred_by_user_id', // fillable
'referred_by_user_id' => 'integer',     // cast

protected static function booted(): void
{
    static::creating(function (User $user) {
        if (empty($user->referral_code)) {
            $user->referral_code = strtoupper(\Illuminate\Support\Str::random(8));
        }
    });
}

public function referrals(): HasMany
{
    return $this->hasMany(User::class, 'referred_by_user_id');
}

public function referredBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'referred_by_user_id');
}

public function referralReward(): HasOne
{
    return $this->hasOne(ReferralReward::class, 'referee_id');
}
```

**`app/Models/ReferralReward.php`** — new model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $fillable = [
        'referrer_id', 'referee_id', 'referrer_reward',
        'referee_reward', 'status', 'credited_at',
    ];
    protected $casts = [
        'referrer_reward' => 'decimal:2',
        'referee_reward'  => 'decimal:2',
        'credited_at'     => 'datetime',
    ];

    public function referrer(): BelongsTo { return $this->belongsTo(User::class, 'referrer_id'); }
    public function referee(): BelongsTo  { return $this->belongsTo(User::class, 'referee_id'); }
}
```

**`app/Services/ReferralService.php`** — new service:

```php
namespace App\Services;

use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    public function __construct(protected WalletService $walletService) {}

    /**
     * Link a new user to a referrer by referral code.
     * Call this inside RegisteredUserController::store() after user creation.
     */
    public function linkReferral(User $newUser, ?string $referralCode): void
    {
        if (! $referralCode) return;

        $referrer = User::where('referral_code', $referralCode)
            ->where('id', '!=', $newUser->id)
            ->first();

        if (! $referrer) return;

        $newUser->update(['referred_by_user_id' => $referrer->id]);

        ReferralReward::create([
            'referrer_id'      => $referrer->id,
            'referee_id'       => $newUser->id,
            'referrer_reward'  => (float) config('auction.referral.referrer_reward', 5.0),
            'referee_reward'   => (float) config('auction.referral.referee_reward', 2.5),
            'status'           => 'pending',
        ]);

        // Option A: Credit immediately on registration
        if (config('auction.referral.credit_on', 'registration') === 'registration') {
            $this->creditReward($newUser);
        }
        // Option B: Credit on first bid (handled in HandleBidPlaced listener)
    }

    /**
     * Credit both referrer and referee.
     */
    public function creditReward(User $referee): void
    {
        $reward = ReferralReward::where('referee_id', $referee->id)
            ->where('status', 'pending')
            ->first();

        if (! $reward) return;

        DB::transaction(function () use ($reward, $referee) {
            $referrer = $reward->referrer;

            $this->walletService->deposit(
                $referrer,
                (float) $reward->referrer_reward,
                "Referral bonus — {$referee->name} joined using your link",
            );

            $this->walletService->deposit(
                $referee,
                (float) $reward->referee_reward,
                'Welcome bonus — referral credit',
            );

            $reward->update([
                'status'      => 'credited',
                'credited_at' => now(),
            ]);
        });

        Log::info('ReferralService: rewards credited', [
            'referrer_id' => $reward->referrer_id,
            'referee_id'  => $referee->id,
        ]);
    }
}
```

**`app/Http/Controllers/Auth/RegisteredUserController.php`** — modify `store()`:

```php
$user = User::create([...]);

// Link referral if code provided
$referralCode = $request->input('referral_code') ?? session('referral_code');
app(ReferralService::class)->linkReferral($user, $referralCode);
```

**Capture referral code from URL** — Middleware to store `?ref=CODE` in session:

```php
// app/Http/Middleware/CaptureReferralCode.php
public function handle(Request $request, Closure $next): Response
{
    if ($code = $request->query('ref')) {
        session(['referral_code' => $code]);
    }
    return $next($request);
}
```

Register in `bootstrap/app.php` web group.

**`config/auction.php`** — add:

```php
'referral' => [
    'referrer_reward' => (float) env('REFERRAL_REWARD_REFERRER', 5.0),
    'referee_reward'  => (float) env('REFERRAL_REWARD_REFEREE', 2.5),
    'credit_on'       => env('REFERRAL_CREDIT_ON', 'registration'), // 'registration' | 'first_bid'
],
```

#### 3. API / Routes
No new routes needed for referral mechanics. Add referral stats to seller/user dashboard endpoint.

```php
// User dashboard — referral section
Route::get('/dashboard/referrals', [ReferralController::class, 'index'])->name('user.referrals');
```

#### 4. Frontend (contract)
- User dashboard "Referrals" page: show referral link (`?ref=CODE`), total referrals, total earned.
- Registration form: hidden `referral_code` field pre-filled from session/URL.
- "Share referral link" button with copy-to-clipboard.

---

### D. Dependencies & Risks
- **Abuse prevention:** Self-referral blocked by `where('id', '!=', $newUser->id)`. Multi-account abuse (same IP) requires rate limiting on registration — handled by existing `throttle:6,1` on `/register`.
- **Expired codes:** Codes don't expire by design; can add `referral_code_expires_at` if needed.
- **Credit on first bid:** If using `'credit_on' = 'first_bid'`, hook into `HandleBidPlaced` listener with a check on `ReferralReward::where('referee_id', $bid->user_id)->where('status', 'pending')`.

---

### E. Implementation Steps
1. Migrations: add `referral_code`, `referred_by_user_id` to `users`; create `referral_rewards`.
2. Update `User` model — booted hook, fillable, relationships.
3. Create `ReferralReward` model.
4. Create `ReferralService`.
5. Create `CaptureReferralCode` middleware.
6. Modify `RegisteredUserController::store()`.
7. Add config keys.
8. Create `ReferralController` + route.
9. Write tests covering: valid code links referral, self-referral rejected, duplicate referral rejected.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 45 — User Follow Seller

### A. Feature Overview
Users can follow verified seller accounts. Followers receive notifications when the followed seller publishes new auctions. Seller storefront shows follower count.

**Business goal:** Creates recurring buyer-seller relationships; drives return visits.

---

### B. Current State
- `User` model — no follower/following relationships.
- `AuctionWatcher` exists for per-auction watching but not seller-level following.
- `ProcessKeywordAlerts` dispatches when auctions go active — similar pattern needed for follower alerts.

---

### C. Required Changes

#### 1. Database

```php
Schema::create('seller_followers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
    $table->boolean('notify_new_listings')->default(true);
    $table->timestamps();

    $table->unique(['follower_id', 'seller_id']);
    $table->index(['seller_id', 'notify_new_listings']);
});
```

#### 2. Backend Logic

**`app/Models/User.php`** — add:

```php
public function following(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'seller_followers', 'follower_id', 'seller_id')
        ->withPivot('notify_new_listings')
        ->withTimestamps();
}

public function followers(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'seller_followers', 'seller_id', 'follower_id')
        ->withPivot('notify_new_listings')
        ->withTimestamps();
}

public function getFollowerCountAttribute(): int
{
    return $this->followers()->count();
}
```

**`app/Models/SellerFollower.php`** — simple pivot model for direct queries.

**`app/Http/Controllers/SellerFollowController.php`** — new controller:

```php
public function toggle(Request $request, User $seller): JsonResponse
{
    $user = $request->user();

    if ($user->id === $seller->id) {
        return response()->json(['error' => 'Cannot follow yourself.'], 422);
    }

    if (! $seller->isVerifiedSeller()) {
        return response()->json(['error' => 'User is not a verified seller.'], 422);
    }

    $existing = $user->following()->where('seller_id', $seller->id)->first();

    if ($existing) {
        $user->following()->detach($seller->id);
        return response()->json(['following' => false, 'message' => 'Unfollowed seller.']);
    }

    $user->following()->attach($seller->id, ['notify_new_listings' => true]);
    return response()->json(['following' => true, 'message' => 'Now following seller.']);
}
```

**`app/Jobs/NotifySellerFollowers.php`** — new job:

```php
namespace App\Jobs;

use App\Models\Auction;
use App\Models\User;
use App\Notifications\NewSellerListingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotifySellerFollowers implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public string $queue = 'notifications';

    public function __construct(public readonly int $auctionId) {}

    public function handle(): void
    {
        $auction = Auction::find($this->auctionId);
        if (! $auction || ! $auction->isActive()) return;

        $seller = $auction->seller;

        User::whereHas('following', fn ($q) => $q->where('seller_id', $seller->id)
            ->where('notify_new_listings', true)
        )->where('id', '!=', $seller->id)
        ->chunk(100, function ($followers) use ($auction) {
            foreach ($followers as $follower) {
                try {
                    $follower->notify(new NewSellerListingNotification($auction));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('NotifySellerFollowers failed', [
                        'follower_id' => $follower->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
```

Dispatch from `Auction::booted()` (alongside `ProcessKeywordAlerts`):

```php
static::updated(function (Auction $auction): void {
    if ($auction->wasChanged('status') && $auction->status === self::STATUS_ACTIVE) {
        ProcessKeywordAlerts::dispatch($auction->id)->delay(now()->addSeconds(5));
        NotifySellerFollowers::dispatch($auction->id)->delay(now()->addSeconds(5));
    }
});
```

#### 3. API / Routes

```php
Route::post('/sellers/{user}/follow', [SellerFollowController::class, 'toggle'])
    ->name('sellers.follow')
    ->middleware('auth');
```

#### 4. Frontend (contract)
- Seller storefront: "Follow" / "Unfollow" toggle button + follower count.
- User dashboard: "Following" tab listing followed sellers with latest auction preview.

---

### D. Dependencies & Risks
- Follower notifications fire via `NotifySellerFollowers` job which runs on `notifications` queue — isolated from bid-critical queues.

---

### E. Implementation Steps
1. Migration: create `seller_followers` table.
2. Update `User` model with `following()`, `followers()` relationships.
3. Create `SellerFollowController`.
4. Create `NotifySellerFollowers` job.
5. Create `NewSellerListingNotification`.
6. Dispatch job from `Auction::booted()`.
7. Register route.
8. Write tests.

---

### F. Complexity & Priority
- **Complexity:** Low
- **Priority:** Growth

---

## Feature 46 — User Block User

### A. Feature Overview
Users can block other users, preventing: direct messaging, seeing blocked user's bids in auction history, receiving notifications from blocked users.

**Business goal:** Safety and harassment prevention.

---

### B. Current State
- No blocking system exists.
- `MessageController` and `ConversationController` have no participant restriction beyond auction ownership.

---

### C. Required Changes

#### 1. Database

```php
Schema::create('user_blocks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
    $table->timestamps();

    $table->unique(['blocker_id', 'blocked_id']);
    $table->index('blocked_id');
});
```

#### 2. Backend Logic

**`app/Models/User.php`** — add:

```php
public function blockedUsers(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id')
        ->withTimestamps();
}

public function blockedByUsers(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'user_blocks', 'blocked_id', 'blocker_id')
        ->withTimestamps();
}

public function hasBlocked(int $userId): bool
{
    return $this->blockedUsers()->where('blocked_id', $userId)->exists();
}

public function isBlockedBy(int $userId): bool
{
    return $this->blockedByUsers()->where('blocker_id', $userId)->exists();
}
```

**`app/Http/Controllers/UserBlockController.php`** — new controller:

```php
public function toggle(Request $request, User $user): JsonResponse
{
    $blocker = $request->user();

    if ($blocker->id === $user->id) {
        return response()->json(['error' => 'Cannot block yourself.'], 422);
    }

    if ($blocker->hasBlocked($user->id)) {
        $blocker->blockedUsers()->detach($user->id);
        return response()->json(['blocked' => false, 'message' => 'User unblocked.']);
    }

    $blocker->blockedUsers()->attach($user->id);
    return response()->json(['blocked' => true, 'message' => 'User blocked.']);
}
```

**`ConversationController::start()`** — add block check:

```php
if ($buyer->hasBlocked($auction->user_id) || $buyer->isBlockedBy($auction->user_id)) {
    return back()->withErrors(['message' => 'You cannot message this user.']);
}
```

**`OutbidNotification::via()`** — skip notification if sender is blocked (check in `HandleBidPlaced` listener before calling `$user->notify()`):

```php
// In HandleBidPlaced::notifyOutbidUser()
if ($previousBid->user->hasBlocked($bid->user_id) || $previousBid->user->isBlockedBy($bid->user_id)) {
    return null; // skip notification
}
```

#### 3. Routes

```php
Route::post('/users/{user}/block', [UserBlockController::class, 'toggle'])
    ->name('users.block')->middleware('auth');
```

#### 4. Frontend (contract)
- Three-dot menu on user profile pages: "Block User" / "Unblock User".
- User settings page: "Blocked Users" list with unblock actions.
- Blocked users' bids shown as "Anonymous Bidder" in auction bid history (optional, configurable).

---

### E. Implementation Steps
1. Migration: create `user_blocks` table.
2. Update `User` model with relationships and helpers.
3. Create `UserBlockController`.
4. Add block checks to `ConversationController` and `HandleBidPlaced`.
5. Register route.
6. Write tests.

---

### F. Complexity & Priority
- **Complexity:** Low
- **Priority:** Growth

---

## Feature 48 — Bid Retraction Request

### A. Feature Overview
Allow bidders to request retraction of their bid under specific conditions (entered wrong amount, genuine error). Requires admin/seller approval. Accepted retractions release escrow; declined retractions hold the bidder to their bid.

**Business goal:** Reduces disputes; provides legitimate error recovery; required by some regulatory frameworks.

---

### B. Current State
- No bid retraction system.
- `Bid` model — no `retracted` flag.
- `EscrowService::releaseForUser()` can release funds.

---

### C. Required Changes

#### 1. Database

```php
Schema::create('bid_retraction_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('bid_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
    $table->text('reason');
    $table->string('status', 20)->default('pending'); // pending, approved, declined
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('reviewed_at')->nullable();
    $table->text('reviewer_notes')->nullable();
    $table->timestamps();

    $table->unique('bid_id'); // one retraction request per bid
    $table->index(['auction_id', 'status']);
    $table->index(['user_id', 'status']);
});

Schema::table('bids', function (Blueprint $table) {
    $table->boolean('is_retracted')->default(false)->after('is_snipe_bid');
    $table->timestamp('retracted_at')->nullable()->after('is_retracted');
});
```

#### 2. Backend Logic

**`app/Models/BidRetractionRequest.php`** — new model with standard fillable/casts/relationships.

**`app/Models/Bid.php`** — add `'is_retracted'`, `'retracted_at'` to fillable and casts.

**`app/Http/Controllers/BidRetractionController.php`** — user-facing:

```php
// store(): create retraction request for owned bid
// Rules: bid must belong to user, auction must be active, bid must be the user's CURRENT highest bid
// (cannot retract a bid that was already outbid — it's irrelevant)
```

**`app/Http/Controllers/Admin/BidRetractionController.php`** — admin action:

```php
public function approve(Request $request, BidRetractionRequest $retractionRequest): JsonResponse
{
    DB::transaction(function () use ($retractionRequest, $request) {
        $bid     = $retractionRequest->bid;
        $auction = $retractionRequest->auction;
        $user    = $retractionRequest->user;

        // Mark bid as retracted
        $bid->update(['is_retracted' => true, 'retracted_at' => now()]);

        // Release escrow
        app(EscrowService::class)->releaseForUser($user, $auction);

        // Recalculate auction current price (next highest non-retracted bid)
        $newHighestBid = Bid::where('auction_id', $auction->id)
            ->where('is_retracted', false)
            ->orderByDesc('amount')
            ->first();

        $auction->update([
            'current_price' => $newHighestBid?->amount ?? $auction->starting_price,
        ]);

        // Update Redis price
        app(BiddingStrategy::class)->initializePrice($auction);

        $retractionRequest->update([
            'status'         => 'approved',
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
            'reviewer_notes' => $request->input('notes'),
        ]);

        AuditLog::record('bid.retraction.approved', 'bid', $bid->id, [
            'auction_id' => $auction->id,
            'user_id'    => $user->id,
        ]);
    });

    return response()->json(['message' => 'Bid retraction approved.']);
}
```

#### 3. Routes

```php
// Authenticated user
Route::post('/bids/{bid}/retract', [BidRetractionController::class, 'store'])->name('bids.retract');

// Admin
Route::get('/admin/bid-retractions', [Admin\BidRetractionController::class, 'index'])->name('admin.bid-retractions.index');
Route::post('/admin/bid-retractions/{request}/approve', [Admin\BidRetractionController::class, 'approve'])->name('admin.bid-retractions.approve');
Route::post('/admin/bid-retractions/{request}/decline', [Admin\BidRetractionController::class, 'decline'])->name('admin.bid-retractions.decline');
```

---

### D. Dependencies & Risks
- **Current price recalculation:** After retraction, the auction current price reverts to the next valid bid. Must also update Redis via `initializePrice()`.
- **Timing:** Retraction requests filed in the last 30 seconds of an auction should be auto-declined (race condition risk).
- **Scope:** Only the user's highest bid on an auction can be retracted — if they've been outbid, their escrow is already released by `HandleBidPlaced`.

---

### E. Implementation Steps
1. Migrations: create `bid_retraction_requests`; add `is_retracted`, `retracted_at` to `bids`.
2. Create `BidRetractionRequest` model.
3. Update `Bid` model.
4. Create user-facing `BidRetractionController` (store).
5. Create admin `BidRetractionController` (index, approve, decline).
6. Implement price recalculation + Redis sync in `approve()`.
7. Register routes.
8. Write tests.

---

### F. Complexity & Priority
- **Complexity:** High
- **Priority:** Growth

---

### A. Feature Overview
Users can earn or purchase "bid credits" that unlock temporary advantages: extended auto-bid limits, early auction access, or free listing fee credits. Credits are a virtual currency separate from wallet balance.

**Business goal:** Gamification; additional revenue stream; increases platform stickiness.

---

### B. Current State
- `WalletTransaction` handles real money.
- No virtual credit/points system exists.

---

### C. Required Changes

#### 1. Database

```php
// Add bid credits balance to users
Schema::table('users', function (Blueprint $table) {
    $table->unsignedInteger('bid_credits')->default(0)->after('held_balance');
});

Schema::create('bid_credit_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('amount');           // positive = earned, negative = spent
    $table->integer('balance_after');
    $table->string('type', 30);          // 'purchase', 'referral_bonus', 'daily_login', 'spent_auto_bid', 'spent_listing'
    $table->string('description')->nullable();
    $table->nullableMorphs('reference'); // what triggered this
    $table->timestamps();

    $table->index(['user_id', 'created_at']);
});

Schema::create('power_up_definitions', function (Blueprint $table) {
    $table->id();
    $table->string('key', 50)->unique();  // 'extra_auto_bid', 'listing_fee_waiver', 'early_access'
    $table->string('name', 100);
    $table->text('description')->nullable();
    $table->unsignedInteger('credit_cost');
    $table->json('effect_data')->nullable(); // {"extra_auto_bids": 5}, {"listing_fee_waiver": true}
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### 2. Backend Logic

**`app/Services/BidCreditService.php`** — new service:

```php
namespace App\Services;

use App\Models\User;
use App\Models\BidCreditTransaction;
use Illuminate\Support\Facades\DB;

class BidCreditService
{
    public function award(User $user, int $amount, string $type, string $description, $reference = null): BidCreditTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $description, $reference) {
            $user->increment('bid_credits', $amount);
            $user->refresh();
            return BidCreditTransaction::create([
                'user_id'        => $user->id,
                'amount'         => $amount,
                'balance_after'  => $user->bid_credits,
                'type'           => $type,
                'description'    => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id'   => $reference?->id,
            ]);
        });
    }

    public function spend(User $user, int $amount, string $type, string $description): BidCreditTransaction
    {
        if ($user->bid_credits < $amount) {
            throw new \DomainException('Insufficient bid credits.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description) {
            $user->decrement('bid_credits', $amount);
            $user->refresh();
            return BidCreditTransaction::create([
                'user_id'       => $user->id,
                'amount'        => -$amount,
                'balance_after' => $user->bid_credits,
                'type'          => $type,
                'description'   => $description,
            ]);
        });
    }

    public function activatePowerUp(User $user, string $powerUpKey): void
    {
        $definition = \App\Models\PowerUpDefinition::where('key', $powerUpKey)
            ->where('is_active', true)
            ->firstOrFail();

        $this->spend($user, $definition->credit_cost, 'spent_' . $powerUpKey, "Activated: {$definition->name}");

        // Apply effect — example for extra auto-bid slots
        $effect = $definition->effect_data;
        if (isset($effect['extra_auto_bids'])) {
            // Increase max_auto_bids on active auto-bids for this user
            \App\Models\AutoBid::where('user_id', $user->id)
                ->where('is_active', true)
                ->increment('max_auto_bids', (int) $effect['extra_auto_bids']);
        }
    }
}
```

#### 3. Routes

```php
Route::get('/dashboard/credits',              [BidCreditController::class, 'index'])->name('user.credits');
Route::post('/dashboard/credits/power-up',    [BidCreditController::class, 'activatePowerUp'])->name('user.credits.power-up');
```

#### 4. Frontend (contract)
- Credit balance shown in user dashboard header.
- "Power-ups" page listing available power-ups with credit costs.
- Transaction history of credits earned/spent.

---

### D. Dependencies & Risks
- **Credit inflation:** Award credits sparingly; define earning caps (e.g., max 10 daily-login credits per day).
- **Audit trail:** All credit movements recorded in `bid_credit_transactions`.

---

### E. Implementation Steps
1. Migrations: add `bid_credits` to `users`; create `bid_credit_transactions`; create `power_up_definitions`.
2. Seed default power-up definitions.
3. Create `BidCreditService`.
4. Create `BidCreditController`.
5. Register routes.
6. Hook credit awards into referral program, daily login (via scheduled command), etc.
7. Write tests.

---

### F. Complexity & Priority
- **Complexity:** High
- **Priority:** Scaling

---

## Feature 55 — Outbid Threshold Alerts (Custom Amount)

### A. Feature Overview
Users set a personal threshold: "Only notify me when I'm outbid by more than $X" or "Notify me when the price exceeds $Y". Reduces notification fatigue for active bidders.

**Business goal:** Improves notification relevance; reduces unsubscribes.

---

### B. Current State
- `OutbidNotification` always fires when any outbid occurs.
- `AuctionWatcher` has `notify_outbid` boolean — binary on/off only.
- `HandleBidPlaced::notifyOutbidUser()` sends to the previous highest bidder unconditionally.

---

### C. Required Changes

#### 1. Database

```php
Schema::table('auction_watchers', function (Blueprint $table) {
    $table->decimal('outbid_threshold_amount', 10, 2)->nullable()->after('notify_outbid');
    // NULL = notify on any outbid; >0 = only notify if outbid by at least this amount
    $table->decimal('price_alert_at', 10, 2)->nullable()->after('outbid_threshold_amount');
    // NULL = no price alert; >0 = notify when current_price exceeds this value
    $table->boolean('price_alert_sent')->default(false)->after('price_alert_at');
});

// Per-user global threshold (applies when no watcher row exists)
Schema::table('users', function (Blueprint $table) {
    $table->decimal('default_outbid_threshold', 10, 2)->nullable()->after('bid_credits');
});
```

#### 2. Backend Logic

**`app/Models/AuctionWatcher.php`** — add fillable:

```php
'outbid_threshold_amount', 'price_alert_at', 'price_alert_sent',
```

**`app/Listeners/HandleBidPlaced.php`** — modify `notifyOutbidUser()`:

```php
protected function shouldNotifyOutbidUser(Bid $newBid, Bid $previousBid, $auction): bool
{
    // Check threshold
    $outbidAmount = (float) $newBid->amount - (float) $previousBid->amount;

    // Check watcher threshold
    $watcher = AuctionWatcher::where('auction_id', $auction->id)
        ->where('user_id', $previousBid->user_id)
        ->first();

    $threshold = $watcher?->outbid_threshold_amount
        ?? $previousBid->user?->default_outbid_threshold;

    if ($threshold !== null && $outbidAmount < (float) $threshold) {
        return false; // Below threshold — skip notification
    }

    return true;
}
```

**Price alert** — check after bid placed:

```php
protected function checkPriceAlerts(Bid $bid, $auction): void
{
    AuctionWatcher::where('auction_id', $auction->id)
        ->where('price_alert_sent', false)
        ->whereNotNull('price_alert_at')
        ->where('price_alert_at', '<=', $bid->amount)
        ->with('user')
        ->get()
        ->each(function ($watcher) use ($auction, $bid) {
            $watcher->user?->notify(new \App\Notifications\PriceAlertNotification(
                $auction->id,
                $auction->title,
                (float) $bid->amount,
                (float) $watcher->price_alert_at,
            ));
            $watcher->update(['price_alert_sent' => true]);
        });
}
```

Call `$this->checkPriceAlerts($bid, $auction)` in `HandleBidPlaced::handle()`.

**`app/Http/Controllers/AuctionController::toggleWatch()`** — extend to accept threshold params:

```php
AuctionWatcher::create([
    'auction_id'             => $auction->id,
    'user_id'                => $user->id,
    'notify_outbid'          => true,
    'notify_ending'          => true,
    'notify_cancelled'       => true,
    'outbid_threshold_amount'=> $request->input('outbid_threshold'),
    'price_alert_at'         => $request->input('price_alert_at'),
]);
```

#### 3. Routes
No new routes — update existing `toggleWatch` to accept threshold params in request body.

#### 4. Frontend (contract)
- Watch button dialog: optional fields "Notify me only if outbid by more than $__" and "Alert me when price reaches $__".
- User notification preferences page: "Default outbid threshold $__".

---

### E. Implementation Steps
1. Migrations: add threshold columns to `auction_watchers`; add `default_outbid_threshold` to `users`.
2. Update `AuctionWatcher` and `User` models.
3. Update `HandleBidPlaced::notifyOutbidUser()` to apply threshold.
4. Add `checkPriceAlerts()` method to `HandleBidPlaced`.
5. Create `PriceAlertNotification`.
6. Update `AuctionController::toggleWatch()`.
7. Write tests.

---

### F. Complexity & Priority
- **Complexity:** Low–Medium
- **Priority:** Growth

---

## Feature 56 — User Language / Locale Preference

### A. Feature Overview
Users select their preferred language for the UI. The application serves translated strings via Laravel's localisation system.

**Business goal:** International market expansion.

---

### B. Current State
- `UserPreference` model has `timezone` field.
- No `locale` column on `users` or `user_preferences`.
- Laravel has built-in localisation (`app()->setLocale()`).
- No translation files exist beyond defaults.

---

### C. Required Changes

#### 1. Database

```php
Schema::table('user_preferences', function (Blueprint $table) {
    $table->string('locale', 10)->default('en')->after('timezone');
});
```

#### 2. Backend Logic

**`app/Http/Middleware/SetUserLocale.php`** — new middleware:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $locale = $user->userPreference?->locale ?? 'en';
        } else {
            $locale = $request->getPreferredLanguage(config('app.supported_locales', ['en']));
        }

        app()->setLocale($locale);
        return $next($request);
    }
}
```

Register in `bootstrap/app.php` web group.

**`config/app.php`** — add:

```php
'supported_locales' => ['en', 'vi', 'ja', 'fr', 'de', 'es'],
```

**`NotificationPreferenceController::update()`** — extend to save locale from preferences form.

#### 3. Routes
No new routes — extend existing notification preferences update endpoint.

---

### D. Dependencies & Risks
- Requires creating translation files in `lang/` directory for each supported locale.
- Currency display (`number_format`) should also respect locale.

---

### E. Implementation Steps
1. Migration: add `locale` to `user_preferences`.
2. Create `SetUserLocale` middleware and register.
3. Add `supported_locales` to `config/app.php`.
4. Update `UserPreference` model.
5. Create base translation files (`lang/en`, `lang/vi`, etc.).
6. Write test verifying locale switches correctly.

---

### F. Complexity & Priority
- **Complexity:** Medium (mostly translation file creation)
- **Priority:** Scaling

---

## Feature 58 — User Account Deactivation (Soft Delete)

### A. Feature Overview
Users can deactivate their account. Deactivated accounts cannot log in; their public data (bids, reviews) is anonymised. Reactivation is possible within a grace period.

**Business goal:** GDPR compliance; safer alternative to full deletion; reduces churn.

---

### B. Current State
- `User` model does NOT use `SoftDeletes` — hard deletion only via `ProfileController::destroy()`.
- `is_banned` flag exists but banning is admin-only; self-deactivation is absent.
- Profile deletion in `ProfileController::destroy()` hard-deletes the user, breaking referential integrity (`bids`, `auctions` FK on `user_id CASCADE`).

---

### C. Required Changes

#### 1. Database

```php
// Migration: add soft deletes and deactivation fields to users
Schema::table('users', function (Blueprint $table) {
    $table->softDeletes();                                              // deleted_at
    $table->boolean('is_deactivated')->default(false)->after('is_banned');
    $table->timestamp('deactivated_at')->nullable()->after('is_deactivated');
    $table->timestamp('reactivation_deadline')->nullable()->after('deactivated_at');
    $table->index('is_deactivated');
});
```

#### 2. Backend Logic

**`app/Models/User.php`** — add:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

// In class body:
use SoftDeletes;

// Fillable additions:
'is_deactivated', 'deactivated_at', 'reactivation_deadline',

// Cast additions:
'is_deactivated'        => 'boolean',
'deactivated_at'        => 'datetime',
'reactivation_deadline' => 'datetime',
```

**`app/Http/Controllers/ProfileController.php`** — replace `destroy()` with deactivation:

```php
public function deactivate(Request $request): RedirectResponse
{
    $request->validateWithBag('userDeletion', [
        'password' => ['required', 'current_password'],
    ]);

    $user = $request->user();

    // Cancel active bids / auctions first
    // (seller: cancel active auctions; bidder: release escrow on open auctions)
    // ... call VacationModeService if seller, or just block login ...

    $user->update([
        'is_deactivated'        => true,
        'deactivated_at'        => now(),
        'reactivation_deadline' => now()->addDays(30),
    ]);

    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/')->with('status', 'Account deactivated. You can reactivate within 30 days.');
}

public function destroy(Request $request): RedirectResponse
{
    // Full permanent deletion (GDPR right to erasure)
    $request->validateWithBag('userDeletion', [
        'password' => ['required', 'current_password'],
        'confirm_delete' => ['required', 'accepted'],
    ]);

    $user = $request->user();

    Auth::logout();

    // Anonymise user data instead of cascade delete to preserve bid/auction history integrity
    $user->update([
        'name'  => 'Deleted User #' . $user->id,
        'email' => 'deleted-' . $user->id . '@deleted.invalid',
    ]);
    $user->delete(); // SoftDelete — sets deleted_at

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
}
```

**`app/Http/Middleware/EnsureNotBanned.php`** — extend to check deactivation:

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if ($user?->isBanned()) {
        // ... existing logout logic ...
    }

    if ($user?->is_deactivated) {
        // Allow reactivation — redirect to reactivation page
        Auth::guard('web')->logout();
        return redirect()->route('account.reactivate')
            ->with('status', 'Your account is deactivated. Would you like to reactivate it?');
    }

    return $next($request);
}
```

**Reactivation controller:**

```php
public function reactivate(Request $request): RedirectResponse
{
    // User must authenticate first (login with deactivated account)
    $user = $request->user();

    if (! $user->is_deactivated) {
        return redirect()->route('dashboard');
    }

    if ($user->reactivation_deadline && now()->isAfter($user->reactivation_deadline)) {
        return redirect('/')->with('error', 'Reactivation period has expired. Account permanently deleted.');
        // Actually delete (GDPR): $user->forceDelete();
    }

    $user->update([
        'is_deactivated'        => false,
        'deactivated_at'        => null,
        'reactivation_deadline' => null,
    ]);

    return redirect()->route('dashboard')->with('status', 'Account reactivated successfully.');
}
```

**Scheduled cleanup** — purge expired deactivated accounts:

```php
Schedule::call(function () {
    User::where('is_deactivated', true)
        ->where('reactivation_deadline', '<=', now())
        ->each(fn ($u) => $u->forceDelete());
})->daily()->name('purge-deactivated-accounts');
```

#### 3. Routes

```php
Route::post('/profile/deactivate',  [ProfileController::class, 'deactivate'])->name('profile.deactivate')->middleware('auth');
Route::get('/account/reactivate',   [ProfileController::class, 'showReactivate'])->name('account.reactivate');
Route::post('/account/reactivate',  [ProfileController::class, 'reactivate'])->name('account.reactivate.store')->middleware('auth');
```

---

### D. Dependencies & Risks
- **FK integrity:** `SoftDeletes` sets `deleted_at` — Eloquent automatically scopes queries to exclude soft-deleted users. Bids and auctions referencing the user via `user_id` remain intact (data preserved for financial/audit integrity).
- **Login prevention:** `EnsureNotBanned` middleware extended to intercept deactivated users before they reach protected routes.

---

### E. Implementation Steps
1. Migration: add `SoftDeletes` + deactivation columns to `users`.
2. Add `use SoftDeletes` to `User` model; update fillable/casts.
3. Replace/augment `ProfileController::destroy()` with `deactivate()`.
4. Create `reactivate()` and `showReactivate()` controller methods.
5. Update `EnsureNotBanned` middleware to handle deactivated accounts.
6. Add scheduler for purging expired accounts.
7. Register new routes.
8. Write tests covering: deactivation blocks login, reactivation restores access, expired accounts purged.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** MVP

---

## Feature 59 — User Export Data (GDPR Compliance)

### A. Feature Overview
Provide users with a downloadable archive of all their personal data: account info, bids, auctions, messages, wallet transactions, notifications.

**Business goal:** GDPR Article 20 (right to data portability); required for EU-accessible platforms.

---

### B. Current State
- `RevenueController::export()` exports seller revenue CSV.
- `WalletController::exportTransactions()` exports wallet CSV.
- No comprehensive personal data export exists.

---

### C. Required Changes

#### 1. Database

```php
Schema::create('data_export_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('status', 20)->default('pending'); // pending, processing, ready, expired
    $table->string('file_path')->nullable();
    $table->timestamp('ready_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'status']);
});
```

#### 2. Backend Logic

**`app/Jobs/GenerateUserDataExport.php`** — queued job (data export can be large):

```php
namespace App\Jobs;

use App\Models\DataExportRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use ZipArchive;

class GenerateUserDataExport implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes max

    public function __construct(public int $exportRequestId) {}

    public function handle(): void
    {
        $request = DataExportRequest::findOrFail($this->exportRequestId);
        $user    = User::findOrFail($request->user_id);

        $request->update(['status' => 'processing']);

        // Build data arrays
        $data = [
            'account'       => $this->exportAccount($user),
            'bids'          => $this->exportBids($user),
            'auctions'      => $this->exportAuctions($user),
            'messages'      => $this->exportMessages($user),
            'wallet'        => $this->exportWallet($user),
            'notifications' => $this->exportNotifications($user),
        ];

        // Create ZIP with CSV files
        $dir      = storage_path("app/private/exports/{$user->id}");
        @mkdir($dir, 0755, true);
        $zipPath  = "{$dir}/data-export-{$user->id}-" . now()->format('Ymd') . ".zip";

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($data as $section => $rows) {
            if (empty($rows)) continue;
            $csvContent = $this->toCsv(array_keys($rows[0] ?? []), $rows);
            $zip->addFromString("{$section}.csv", $csvContent);
        }

        $zip->close();

        $relativePath = "private/exports/{$user->id}/" . basename($zipPath);
        $request->update([
            'status'    => 'ready',
            'file_path' => $relativePath,
            'ready_at'  => now(),
            'expires_at'=> now()->addDays(7),
        ]);
    }

    private function exportAccount(User $user): array
    {
        return [[
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'joined_at'  => $user->created_at->toIso8601String(),
            'seller_bio' => $user->seller_bio,
        ]];
    }

    private function exportBids(User $user): array
    {
        return $user->bids()
            ->with('auction:id,title')
            ->orderBy('created_at')
            ->get(['auction_id', 'amount', 'bid_type', 'created_at'])
            ->map(fn ($b) => [
                'auction_id'    => $b->auction_id,
                'auction_title' => $b->auction?->title,
                'amount'        => (float) $b->amount,
                'type'          => $b->bid_type,
                'placed_at'     => $b->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function exportWallet(User $user): array
    {
        return $user->walletTransactions()
            ->orderBy('created_at')
            ->get(['type', 'amount', 'balance_after', 'description', 'created_at'])
            ->map(fn ($t) => [
                'type'          => $t->type,
                'amount'        => (float) $t->amount,
                'balance_after' => (float) $t->balance_after,
                'description'   => $t->description,
                'date'          => $t->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function exportMessages(User $user): array
    {
        return $user->sentMessages()
            ->with('conversation.auction:id,title')
            ->orderBy('created_at')
            ->get(['body', 'created_at', 'conversation_id'])
            ->map(fn ($m) => [
                'conversation_id' => $m->conversation_id,
                'auction_title'   => $m->conversation?->auction?->title,
                'body'            => $m->body,
                'sent_at'         => $m->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function exportAuctions(User $user): array
    {
        return $user->auctions()
            ->orderBy('created_at')
            ->get(['id', 'title', 'status', 'starting_price', 'current_price', 'created_at'])
            ->map(fn ($a) => [
                'id'             => $a->id,
                'title'          => $a->title,
                'status'         => $a->status,
                'starting_price' => (float) $a->starting_price,
                'final_price'    => (float) $a->current_price,
                'created_at'     => $a->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function exportNotifications(User $user): array
    {
        return $user->notifications()
            ->orderBy('created_at')
            ->get(['type', 'data', 'read_at', 'created_at'])
            ->map(fn ($n) => [
                'type'       => class_basename($n->type),
                'message'    => $n->data['message'] ?? '',
                'read'       => $n->read_at ? 'yes' : 'no',
                'created_at' => $n->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function toCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return $content;
    }
}
```

**Controller:**

```php
// User requests export
public function requestExport(Request $request)
{
    $existing = DataExportRequest::where('user_id', $request->user()->id)
        ->whereIn('status', ['pending', 'processing', 'ready'])
        ->first();

    if ($existing && $existing->status === 'ready') {
        return redirect()->route('user.data-export.download', $existing);
    }

    if ($existing) {
        return back()->with('status', 'Your export is being prepared. Check back soon.');
    }

    $exportRequest = DataExportRequest::create([
        'user_id' => $request->user()->id,
        'status'  => 'pending',
    ]);

    GenerateUserDataExport::dispatch($exportRequest->id)->onQueue('default');

    return back()->with('status', 'Data export requested. You will be notified when it\'s ready.');
}
```

#### 3. Routes

```php
Route::post('/dashboard/data-export',              [DataExportController::class, 'requestExport'])->name('user.data-export.request');
Route::get('/dashboard/data-export/{request}/download', [DataExportController::class, 'download'])->name('user.data-export.download');
```

---

### D. Dependencies & Risks
- **File size:** Large accounts may produce 10+ MB ZIPs. Store in `private` disk, not `public`.
- **Rate limiting:** Cap to 1 export request per 24 hours per user.
- **Expiry:** Exported ZIPs expire in 7 days; a scheduler should delete them.

---

### E. Implementation Steps
1. Migration: create `data_export_requests` table.
2. Create `DataExportRequest` model.
3. Create `GenerateUserDataExport` job.
4. Create `DataExportController`.
5. Register routes.
6. Add export cleanup to scheduler (delete expired ZIPs).
7. Notify user via database notification when export is ready.
8. Write integration test.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** MVP (GDPR requirement)

---

## Shared Components

| Component | Used By Features |
|-----------|-----------------|
| `WalletService::deposit()` | Referral (43), Credits (50) |
| `EscrowService::releaseForUser()` | Bid Retraction (48) |
| `AuditLog::record()` | All write operations |
| `EnsureNotBanned` middleware | Account Deactivation (58) |
| Database notifications | All notification features |
| Scheduler | Vacation expiry (80), Export cleanup (59), Deactivation purge (58) |

## Quick Wins (≤ 1 day each)
- Feature 56 (Locale): 1 migration + 1 middleware
- Feature 55 (Thresholds): 2 column additions + logic in existing listener
- Feature 45 (Follow Seller): 1 table + 1 job + 1 controller

## Architectural Notes
1. **`UserPrivacyService`**: Consider grouping features 58, 59, and 46 (block) into a single `UserPrivacyService` — all deal with user data rights.
2. **Notification preference expansion**: Features 55 requires extending the notification preference system — do this alongside Feature 56 (locale) in a single "User Preferences" sprint.