# General & Auction Features — Implementation Plan

> Codebase baseline: Laravel 12, PostgreSQL, Redis (Lua atomic bidding), Reverb WebSockets, Horizon queues, Spatie MediaLibrary, Stripe payments.

---

## Feature 7 — Buy It Now (Instant Purchase) Option

### A. Feature Overview
Allow sellers to set a fixed "Buy It Now" (BIN) price on an auction. Any bidder who pays that price immediately wins the auction, bypassing the normal bidding timeline. BIN disappears once any bid exceeds a configurable threshold (typically 80 % of BIN price) or once a bid is placed (depending on business rule).

**Business goal:** Increases conversion for price-sensitive buyers; reduces unsold inventory.

---

### B. Current State
- `Auction` model has `starting_price`, `current_price`, `reserve_price`, `reserve_met`.
- `BiddingStrategy::placeBid()` exists in both `RedisAtomicEngine` and `PessimisticSqlEngine`.
- No `buy_it_now_price` column, no BIN purchase flow, no special route or controller action.

---

### C. Required Changes

#### 1. Database

```sql
-- Migration: add_buy_it_now_to_auctions_table
ALTER TABLE auctions
  ADD COLUMN buy_it_now_price   DECIMAL(15,2) NULL,
  ADD COLUMN buy_it_now_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN buy_it_now_expires_at TIMESTAMP NULL; -- NULL = never auto-expire
```

Migration file: `2026_xx_xx_add_buy_it_now_to_auctions_table.php`

```php
Schema::table('auctions', function (Blueprint $table) {
    $table->decimal('buy_it_now_price', 15, 2)->nullable()->after('reserve_price');
    $table->boolean('buy_it_now_enabled')->default(false)->after('buy_it_now_price');
    $table->timestamp('buy_it_now_expires_at')->nullable()->after('buy_it_now_enabled');
    $table->index(['status', 'buy_it_now_enabled']);
});
```

No separate `bin_purchases` table needed — a completed auction with `payment_status = 'paid'` and a new `win_method` column suffices.

```php
$table->string('win_method', 20)->default('bid')->after('winning_bid_amount');
// values: 'bid', 'buy_it_now'
```

#### 2. Backend Logic

**`app/Models/Auction.php`** — add helpers:

```php
// Fillable additions
'buy_it_now_price', 'buy_it_now_enabled', 'buy_it_now_expires_at', 'win_method',

// Casts additions
'buy_it_now_price'   => 'decimal:2',
'buy_it_now_enabled' => 'boolean',
'buy_it_now_expires_at' => 'datetime',

// Helper methods
public function hasBuyItNow(): bool
{
    return $this->buy_it_now_enabled
        && $this->buy_it_now_price !== null
        && ($this->buy_it_now_expires_at === null || $this->buy_it_now_expires_at->isFuture());
}

// BIN disappears once current_price exceeds threshold (e.g. 75 % of BIN)
public function isBuyItNowAvailable(): bool
{
    if (! $this->hasBuyItNow()) {
        return false;
    }
    $threshold = config('auction.buy_it_now.bid_threshold_pct', 75) / 100;
    return (float) $this->current_price < ((float) $this->buy_it_now_price * $threshold);
}
```

**`app/Services/BuyItNowService.php`** — new service:

```php
namespace App\Services;

use App\Models\Auction;
use App\Models\User;
use App\Models\Invoice;
use App\Events\AuctionClosed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyItNowService
{
    public function __construct(
        protected EscrowService  $escrowService,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Atomically process a Buy It Now purchase.
     * Closes the auction immediately, sets winner, captures payment.
     */
    public function purchase(Auction $auction, User $buyer): Invoice
    {
        if (! $auction->isBuyItNowAvailable()) {
            throw new \DomainException('Buy It Now is no longer available for this auction.');
        }

        if ($auction->user_id === $buyer->id) {
            throw new \DomainException('You cannot buy your own auction.');
        }

        $price = (float) $auction->buy_it_now_price;

        if (! $buyer->canAfford($price)) {
            throw new \DomainException('Insufficient wallet balance.');
        }

        return DB::transaction(function () use ($auction, $buyer, $price) {
            // Re-lock
            $locked = Auction::lockForUpdate()->findOrFail($auction->id);

            if ($locked->status !== Auction::STATUS_ACTIVE || ! $locked->isBuyItNowAvailable()) {
                throw new \DomainException('Buy It Now is no longer available.');
            }

            // Hold funds
            $this->escrowService->holdForBid($buyer, $locked, $price);

            // Close auction
            $locked->status              = Auction::STATUS_COMPLETED;
            $locked->winner_id           = $buyer->id;
            $locked->winning_bid_amount  = $price;
            $locked->current_price       = $price;
            $locked->win_method          = 'buy_it_now';
            $locked->closed_at           = now();
            $locked->buy_it_now_enabled  = false;
            $locked->save();

            // Capture payment immediately
            $invoice = $this->paymentService->captureWinnerPayment($locked);

            Log::info('BuyItNowService: purchase completed', [
                'auction_id' => $locked->id,
                'buyer_id'   => $buyer->id,
                'price'      => $price,
                'invoice_id' => $invoice->id,
            ]);

            AuctionClosed::dispatch($locked->fresh());

            return $invoice;
        });
    }
}
```

**`app/Http/Controllers/BuyItNowController.php`** — new controller:

```php
namespace App\Http\Controllers;

use App\Models\Auction;
use App\Services\BuyItNowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyItNowController extends Controller
{
    public function __construct(protected BuyItNowService $service) {}

    public function purchase(Request $request, Auction $auction): JsonResponse
    {
        try {
            $invoice = $this->service->purchase($auction, $request->user());
            return response()->json([
                'success'    => true,
                'message'    => 'Purchase successful! You won this auction.',
                'invoice_id' => $invoice->id,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
```

**Seller auction creation/editing** — add BIN fields to `StoreAuctionRequest` and `UpdateAuctionRequest`:

```php
'buy_it_now_price'      => ['nullable', 'numeric', 'gt:starting_price'],
'buy_it_now_enabled'    => ['nullable', 'boolean'],
'buy_it_now_expires_at' => ['nullable', 'date', 'after:now'],
```

Validation rule: `buy_it_now_price` must be greater than `starting_price` and ideally greater than `reserve_price`.

**`config/auction.php`** — add:

```php
'buy_it_now' => [
    'bid_threshold_pct' => (int) env('AUCTION_BIN_THRESHOLD_PCT', 75),
    // Once current_price exceeds 75% of BIN, the BIN option disappears
],
```

#### 3. API / Routes

```php
// routes/web.php — inside auth middleware group
Route::post('/auctions/{auction}/buy-it-now', [BuyItNowController::class, 'purchase'])
    ->name('auctions.buy-it-now');
```

#### 4. Frontend (contract for future implementation)
- Display BIN price badge on auction card and detail page when `isBuyItNowAvailable() === true`.
- "Buy It Now for $X" button — fires POST `/auctions/{id}/buy-it-now`.
- On success, redirect to invoice page.
- Hide BIN button via Reverb real-time update when `bid.placed` event causes BIN to expire.

#### 5. Integrations
- Reuses existing `EscrowService`, `PaymentService`, `AuctionClosed` event, and `HandleAuctionClosed` listener (which handles winner notification and loser escrow release).

---

### D. Dependencies & Risks
- **Race condition:** Two users clicking BIN simultaneously → solved by `lockForUpdate()` in transaction; second attempt sees `buy_it_now_enabled = false`.
- **Active bids:** Existing bidders get notified via `HandleAuctionClosed` listener (they receive `AuctionLostNotification` and escrow released).
- **Redis price key:** After BIN purchase, call `$engine->cleanup($auction)` to remove stale Redis key.

---

### E. Implementation Steps
1. Create migration adding `buy_it_now_price`, `buy_it_now_enabled`, `buy_it_now_expires_at`, `win_method`.
2. Update `Auction` model — fillable, casts, `hasBuyItNow()`, `isBuyItNowAvailable()`.
3. Create `BuyItNowService`.
4. Create `BuyItNowController`.
5. Add BIN validation rules to `StoreAuctionRequest` / `UpdateAuctionRequest`.
6. Update `AuctionCrudController::store()` and `update()` to persist BIN fields.
7. Add route in `routes/web.php`.
8. Add `buy_it_now.bid_threshold_pct` to `config/auction.php`.
9. Ensure `engine->cleanup()` is called inside `BuyItNowService::purchase()` after transaction.
10. Write feature tests.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 8 — Reserve Price Reveal Toggle

### A. Feature Overview
Allow sellers to choose whether the reserve price is publicly visible ("Reveal reserve: $X") or hidden ("Reserve price: Not disclosed"). The `reserve_met` boolean is already shown in events; this feature controls whether the *amount* is revealed.

**Business goal:** Creates psychological urgency when reserve is visible; protects seller strategy when hidden.

---

### B. Current State
- `reserve_price` (decimal) and `reserve_met` (boolean) exist on `Auction`.
- `AuctionClosed` broadcasts `reserve_met` — not the reserve price amount.
- No `reserve_price_visible` toggle exists.

---

### C. Required Changes

#### 1. Database

```php
// Migration
Schema::table('auctions', function (Blueprint $table) {
    $table->boolean('reserve_price_visible')->default(false)->after('reserve_met');
});
```

#### 2. Backend Logic

**`app/Models/Auction.php`** — add:

```php
'reserve_price_visible' => 'boolean', // cast
'reserve_price_visible' // fillable

public function getPublicReservePriceAttribute(): ?string
{
    if (! $this->hasReserve()) {
        return null;
    }
    return $this->reserve_price_visible
        ? number_format((float) $this->reserve_price, 2)
        : null;
}
```

**`app/Http/Resources/AuctionResource.php`** — create a JSON resource that conditionally exposes `reserve_price`:

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuctionResource extends JsonResource
{
    public function toArray($request): array
    {
        $isOwner = $request->user()?->id === $this->user_id;
        return [
            'id'                    => $this->id,
            'title'                 => $this->title,
            'current_price'         => (float) $this->current_price,
            'reserve_met'           => $this->reserve_met,
            // Only reveal reserve_price if seller opts in OR viewer is the seller/admin
            'reserve_price'         => ($this->reserve_price_visible || $isOwner || $request->user()?->isStaff())
                                        ? (float) $this->reserve_price
                                        : null,
            'reserve_price_visible' => $this->reserve_price_visible,
            'has_reserve'           => $this->hasReserve(),
            // ... other fields
        ];
    }
}
```

**Seller form fields** — add `reserve_price_visible` checkbox to `StoreAuctionRequest` / `UpdateAuctionRequest`:

```php
'reserve_price_visible' => ['nullable', 'boolean'],
```

#### 3. API / Routes
No new routes needed. The existing `AuctionController::show()` and `liveState()` should return the resource. The `liveState()` JSON response should use the resource or manually apply the same visibility logic.

#### 4. Frontend (contract)
- In the auction detail page: show "Reserve: $X.XX" if `reserve_price !== null`, else show "Reserve: Not disclosed".
- In seller create/edit form: checkbox "Show reserve price to bidders".
- In bid widget: show "Reserve not yet met" when `reserve_met === false` regardless of visibility.

---

### D. Dependencies & Risks
- Admin panel `AuctionManagementController::show()` should always reveal the reserve price (already does via direct model access — ensure no resource wrapper hides it for admins).
- If `reserve_price_visible` is toggled mid-auction, broadcast a `PriceUpdated` event so the frontend refreshes.

---

### E. Implementation Steps
1. Create migration adding `reserve_price_visible` boolean column.
2. Add to `Auction` fillable and casts.
3. Add `getPublicReservePriceAttribute()` accessor.
4. Create `AuctionResource` JSON resource with conditional reserve exposure.
5. Add validation rule to request classes.
6. Update `AuctionCrudController::store()` and `update()` to persist the field.
7. Update `AuctionController::show()` and `liveState()` to apply visibility logic.
8. Write unit test for accessor logic.

---

### F. Complexity & Priority
- **Complexity:** Low
- **Priority:** MVP

---

## Feature 9 — Auction Re-listing (Clone & Re-post)

### A. Feature Overview
Allow sellers to duplicate a completed or cancelled auction as a new draft, pre-filling all fields (title, description, images, categories, tags, attributes) with the original's data. The seller can adjust settings before publishing.

**Business goal:** Reduces friction for repeat sellers; reduces unsold inventory sitting as cancelled auctions.

---

### B. Current State
- `AuctionCrudController` has `store()`, `update()`, `cancel()`, `destroy()` — no clone action.
- `Auction` model uses Spatie MediaLibrary for images; cloning images requires copying media.
- Categories, tags, attribute values use pivot tables.

---

### C. Required Changes

#### 1. Database
No new tables. Cloned auction is a standard new draft row.

Optional tracking column:

```php
Schema::table('auctions', function (Blueprint $table) {
    $table->unsignedBigInteger('cloned_from_auction_id')->nullable()->after('serial_number');
    $table->foreign('cloned_from_auction_id')->references('id')->on('auctions')->nullOnDelete();
});
```

#### 2. Backend Logic

**`app/Services/AuctionCloneService.php`** — new service:

```php
namespace App\Services;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuctionCloneService
{
    /**
     * Clone an auction as a new draft belonging to $requester.
     */
    public function clone(Auction $source, User $requester): Auction
    {
        // Authorization: only owner or admin may clone
        if ($source->user_id !== $requester->id && ! $requester->isStaff()) {
            throw new \DomainException('You do not have permission to re-list this auction.');
        }

        return DB::transaction(function () use ($source, $requester) {
            $clone = Auction::create([
                'user_id'                  => $requester->id,
                'title'                    => $source->title,
                'description'              => $source->description,
                'starting_price'           => $source->starting_price,
                'current_price'            => $source->starting_price,
                'reserve_price'            => $source->reserve_price,
                'reserve_price_visible'    => $source->reserve_price_visible,
                'reserve_met'              => false,
                'min_bid_increment'        => $source->min_bid_increment,
                'snipe_threshold_seconds'  => $source->snipe_threshold_seconds,
                'snipe_extension_seconds'  => $source->snipe_extension_seconds,
                'max_extensions'           => $source->max_extensions,
                'currency'                 => $source->currency,
                'condition'                => $source->condition,
                'brand_id'                 => $source->brand_id,
                'sku'                      => $source->sku,
                'serial_number'            => $source->serial_number,
                'video_url'                => $source->video_url,
                'buy_it_now_price'         => $source->buy_it_now_price,
                'buy_it_now_enabled'       => false, // seller must re-enable after review
                'status'                   => Auction::STATUS_DRAFT,
                'cloned_from_auction_id'   => $source->id,
                // end_time intentionally null — seller must set new dates
            ]);

            // Clone categories
            $catSync = [];
            foreach ($source->categories as $cat) {
                $catSync[$cat->id] = ['is_primary' => (bool) $cat->pivot->is_primary];
            }
            $clone->categories()->sync($catSync);

            // Clone tags
            $clone->tags()->sync($source->tags->pluck('id')->all());

            // Clone attribute values
            foreach ($source->attributeValues as $av) {
                $clone->attributeValues()->create([
                    'attribute_id' => $av->attribute_id,
                    'value'        => $av->value,
                ]);
            }

            // Clone media — copy files via Spatie
            foreach ($source->getMedia('images') as $media) {
                try {
                    $clone->addMedia($media->getPath())
                          ->preservingOriginal()
                          ->usingFileName($media->file_name)
                          ->toMediaCollection('images');
                } catch (\Throwable $e) {
                    // Log but don't fail entire clone
                    \Illuminate\Support\Facades\Log::warning('AuctionCloneService: media copy failed', [
                        'media_id'   => $media->id,
                        'auction_id' => $source->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            \App\Models\AuditLog::record('auction.cloned', Auction::class, $clone->id, [
                'source_auction_id' => $source->id,
            ]);

            return $clone;
        });
    }
}
```

**`app/Http/Controllers/Seller/AuctionCrudController.php`** — add method:

```php
public function clone(Auction $auction): RedirectResponse
{
    $this->authorize('view', $auction); // seller owns it or admin
    $clone = app(AuctionCloneService::class)->clone($auction, request()->user());
    return redirect()
        ->route('seller.auctions.edit', $clone)
        ->with('status', 'Auction cloned as a new draft. Set new dates before publishing.');
}
```

**`app/Policies/AuctionPolicy.php`** — add clone gate (reuse `view` or create explicit):

```php
public function clone(User $user, Auction $auction): bool
{
    return $auction->user_id === $user->id
        && in_array($auction->status, [Auction::STATUS_COMPLETED, Auction::STATUS_CANCELLED], true);
}
```

#### 3. API / Routes

```php
// Inside seller middleware group
Route::post('/auctions/{auction}/clone', [AuctionCrudController::class, 'clone'])
    ->name('seller.auctions.clone');
```

#### 4. Frontend (contract)
- "Re-list" button on seller auction index and show pages for completed/cancelled auctions.
- POST to `/seller/auctions/{id}/clone` → redirects to the edit form of the new draft.
- Warning banner in edit form: "No end date set — please configure auction schedule before publishing."

---

### D. Dependencies & Risks
- **Media copying:** `addMedia($path)->preservingOriginal()` keeps the original file. If original auction is deleted later, the cloned images remain because Spatie copies the file.
- **Storage costs:** Many clones of large image sets inflate storage. Consider linking vs copying (acceptable trade-off for simplicity).
- **Cloning active auctions:** Policy explicitly restricts cloning to completed/cancelled; active auctions cannot be cloned to prevent confusion.

---

### E. Implementation Steps
1. Migration: add `cloned_from_auction_id` FK column.
2. Update `Auction` fillable/casts.
3. Create `AuctionCloneService`.
4. Add `clone()` method to `AuctionCrudController`.
5. Add `clone` gate to `AuctionPolicy`.
6. Register route in `routes/web.php`.
7. Write feature test covering category/tag/attribute/media propagation.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 14 — Group / Lot Auctions (Bundle Items)

### A. Feature Overview
A "Lot Auction" groups multiple distinct items into a single auction. The winner receives all items in the lot. This differs from a "Bundle" tag — it requires structured item listings with descriptions, quantities, and individual images per lot item.

**Business goal:** Supports estate sales, wholesale lots, collector item bundles.

---

### B. Current State
- A "Bundle" tag exists (`TagSeeder`) — purely cosmetic, no business logic.
- `Auction` has a single description and image collection.
- No `lot_items` concept exists.

---

### C. Required Changes

#### 1. Database

```php
// Migration: create_auction_lot_items_table
Schema::create('auction_lot_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
    $table->string('name', 255);
    $table->text('description')->nullable();
    $table->unsignedInteger('quantity')->default(1);
    $table->string('condition', 20)->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index(['auction_id', 'sort_order']);
});

// Flag on auctions table
Schema::table('auctions', function (Blueprint $table) {
    $table->boolean('is_lot')->default(false)->after('serial_number');
    $table->unsignedInteger('lot_item_count')->default(0)->after('is_lot');
});
```

Lot item images use Spatie MediaLibrary on `AuctionLotItem` model (new morphable model).

#### 2. Backend Logic

**`app/Models/AuctionLotItem.php`** — new model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AuctionLotItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['auction_id', 'name', 'description', 'quantity', 'condition', 'sort_order'];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('item_images')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}
```

**`app/Models/Auction.php`** — add:

```php
// Fillable
'is_lot', 'lot_item_count',

// Casts
'is_lot'          => 'boolean',
'lot_item_count'  => 'integer',

// Relationship
public function lotItems(): HasMany
{
    return $this->hasMany(AuctionLotItem::class)->orderBy('sort_order');
}
```

**`app/Http/Controllers/Seller/AuctionLotItemController.php`** — new controller for CRUD on lot items within an auction draft:

```php
// store, update, destroy, reorder — scope to $auction->lotItems()
// Validates seller owns the auction and auction is in draft/active status
```

**Validation — `StoreAuctionRequest`:**

```php
'is_lot'        => ['nullable', 'boolean'],
'lot_items'     => ['nullable', 'array', 'max:50'],
'lot_items.*.name'        => ['required_with:lot_items', 'string', 'max:255'],
'lot_items.*.quantity'    => ['nullable', 'integer', 'min:1'],
'lot_items.*.description' => ['nullable', 'string', 'max:2000'],
'lot_items.*.condition'   => ['nullable', 'string', Rule::in(array_keys(Auction::CONDITIONS))],
```

**`AuctionCrudController::store()`** — after creating auction, sync lot items if `is_lot`:

```php
if ($validated['is_lot'] ?? false) {
    foreach ($validated['lot_items'] ?? [] as $index => $item) {
        $auction->lotItems()->create([...$item, 'sort_order' => $index]);
    }
    $auction->update(['lot_item_count' => count($validated['lot_items'] ?? [])]);
}
```

#### 3. API / Routes

```php
Route::prefix('/auctions/{auction}/lot-items')->name('seller.auctions.lot-items.')->group(function () {
    Route::post('/',             [AuctionLotItemController::class, 'store'])->name('store');
    Route::patch('/{item}',      [AuctionLotItemController::class, 'update'])->name('update');
    Route::delete('/{item}',     [AuctionLotItemController::class, 'destroy'])->name('destroy');
    Route::post('/reorder',      [AuctionLotItemController::class, 'reorder'])->name('reorder');
    Route::post('/{item}/image', [AuctionLotItemController::class, 'uploadImage'])->name('image');
});
```

#### 4. Frontend (contract)
- In seller create/edit: toggle "This is a Lot Auction" → reveals a dynamic list of lot items with name, qty, condition, description, and image upload per item.
- Public auction detail: display collapsible lot item list below description.
- Auction cards: show "Lot of N items" badge when `is_lot && lot_item_count > 0`.

---

### D. Dependencies & Risks
- **Payment:** Lot auctions behave identically to regular auctions — one winner, one payment.
- **Cloning:** `AuctionCloneService` must also clone `lotItems` and their media.
- **Search:** Elasticsearch (Feature 203) should index lot item names/descriptions for searchability.

---

### E. Implementation Steps
1. Migration: create `auction_lot_items` table + add `is_lot`, `lot_item_count` to `auctions`.
2. Create `AuctionLotItem` model with Spatie media.
3. Update `Auction` model — relationship, fillable, casts.
4. Create `AuctionLotItemController`.
5. Add validation rules to `StoreAuctionRequest` / `UpdateAuctionRequest`.
6. Update `AuctionCrudController::store()` and `update()` to sync lot items.
7. Register routes.
8. Update `AuctionCloneService` to clone lot items.
9. Write feature tests.

---

### F. Complexity & Priority
- **Complexity:** High
- **Priority:** Growth

---

## Feature 21 — Auction Drafts Auto-Save

### A. Feature Overview
Automatically persist draft auction form changes to the server without requiring the seller to click "Save Draft" manually. Prevents data loss on accidental navigation or tab close.

**Business goal:** Reduces seller frustration and draft abandonment; standard UX expectation for content creation tools.

---

### B. Current State
- `Auction::STATUS_DRAFT` exists and `AuctionCrudController::store()` creates drafts.
- No auto-save mechanism — every save is a manual form submission.
- No debounced PATCH endpoint optimised for partial updates.

---

### C. Required Changes

#### 1. Database
No schema changes needed. Existing `auctions.updated_at` tracks last save time.

Optional: track auto-save timestamp separately:

```php
$table->timestamp('auto_saved_at')->nullable()->after('updated_at');
```

#### 2. Backend Logic

**`app/Http/Controllers/Seller/AuctionDraftController.php`** — new lightweight auto-save controller:

```php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuctionDraftController extends Controller
{
    /**
     * Auto-save a draft — accepts partial data, no validation failures returned as errors.
     * Only operates on DRAFT auctions owned by the authenticated seller.
     */
    public function autoSave(Request $request, Auction $auction): JsonResponse
    {
        if ($auction->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if (! $auction->isDraft()) {
            return response()->json(['error' => 'Only drafts can be auto-saved.'], 422);
        }

        // Whitelist of fields safe for auto-save (no status, no pricing changes post-publish)
        $allowed = [
            'title', 'description', 'video_url', 'condition', 'brand_id',
            'sku', 'serial_number', 'reserve_price_visible',
            'snipe_threshold_seconds', 'snipe_extension_seconds', 'max_extensions',
        ];

        $data = Arr::only($request->all(), $allowed);

        if (! empty($data)) {
            $auction->fill($data);
            $auction->auto_saved_at = now();
            $auction->saveQuietly(); // skip model events to avoid cache flushes on every keystroke
        }

        return response()->json([
            'saved'         => true,
            'auto_saved_at' => $auction->auto_saved_at?->toIso8601String(),
        ]);
    }
}
```

**Rate limiting** — auto-save should be throttled server-side. Add to `routes/web.php`:

```php
Route::patch('/seller/auctions/{auction}/auto-save', [AuctionDraftController::class, 'autoSave'])
    ->name('seller.auctions.auto-save')
    ->middleware(['auth', 'seller', 'throttle:30,1']); // max 30 auto-saves per minute
```

#### 3. Frontend (contract)
- Debounce: fire auto-save PATCH 2 seconds after user stops typing in any field.
- Show "Saving…" → "Saved at HH:MM" indicator in the form toolbar.
- On page unload (`beforeunload`), trigger a synchronous auto-save if dirty.
- Store last dirty state in JavaScript; suppress auto-save if no changes detected.

#### 4. No new integrations needed.

---

### D. Dependencies & Risks
- **`saveQuietly()`** skips the `updated` model event, preventing `Cache::forget('featured_auctions')` on each keystroke — intentional.
- **Throttle:** Without rate limiting, a fast typist could generate hundreds of DB writes per minute. The `throttle:30,1` middleware prevents this.
- **Concurrent tabs:** Last-write-wins. No conflict detection needed for drafts (only one seller can edit their own draft).

---

### E. Implementation Steps
1. Add `auto_saved_at` column migration (optional but improves UX feedback).
2. Create `AuctionDraftController` with `autoSave()` method.
3. Add route with throttle middleware.
4. Write frontend debounce logic (deferred to frontend sprint).
5. Write unit test verifying only whitelisted fields are saved and non-draft auctions are rejected.

---

### F. Complexity & Priority
- **Complexity:** Low
- **Priority:** MVP

---

## Feature 22 — Auction Preview Mode Before Publishing

### A. Feature Overview
Allow sellers to view their draft auction exactly as it would appear to public buyers — including images, description, lot items, attributes — before clicking "Publish". No bidding is possible in preview mode.

**Business goal:** Reduces publish-time errors (typos, wrong images, missing fields). Standard e-commerce UX pattern.

---

### B. Current State
- `AuctionCrudController::publish()` validates required fields but provides no visual preview.
- `AuctionController::show()` renders the public auction detail page but only for active/completed auctions (no explicit guard for drafts, but draft URLs aren't exposed to buyers).

---

### C. Required Changes

#### 1. Database
No changes needed.

#### 2. Backend Logic

**`app/Http/Controllers/Seller/AuctionCrudController.php`** — add `preview()`:

```php
public function preview(Auction $auction)
{
    $this->authorize('update', $auction);

    if (! $auction->isDraft()) {
        return redirect()->route('auctions.show', $auction);
    }

    $auction->load([
        'seller', 'categories', 'media', 'brand', 'tags',
        'attributeValues.attribute', 'lotItems',
    ]);

    // Inject a mock state — no real bidding data exists for drafts
    $isWatching   = false;
    $autoBid      = null;
    $recentBids   = collect();
    $bidChartData = '[]';
    $questions    = collect();
    $prediction   = null;
    $isPreview    = true; // flag for the view to suppress bid form

    return view('auctions.show', compact(
        'auction', 'isWatching', 'autoBid', 'recentBids',
        'bidChartData', 'questions', 'prediction', 'isPreview',
    ));
}
```

**`app/Policies/AuctionPolicy.php`** — the existing `update` gate already checks `$auction->user_id === $user->id`, so reusing it is correct.

#### 3. API / Routes

```php
// Inside seller middleware group
Route::get('/auctions/{auction}/preview', [AuctionCrudController::class, 'preview'])
    ->name('seller.auctions.preview');
```

#### 4. Frontend (contract)
- "Preview" button on the seller edit form redirects to `/seller/auctions/{id}/preview`.
- The auction detail view (`auctions.show`) checks `$isPreview`:
  - Show a prominent yellow banner: "📋 Preview Mode — this auction is not yet published."
  - Hide bid form, BIN button, watch button, report button.
  - Show "← Back to Edit" and "Publish Now" action buttons.
- Breadcrumb: Seller Dashboard → My Auctions → Edit → Preview.

---

### D. Dependencies & Risks
- **Stub data:** A draft auction has no bids, no `current_price` beyond `starting_price`. The preview uses `starting_price` as the displayed current price.
- **Price prediction:** `AttributePricePredictionService::predict()` is called in the real `show()` action — it can run in preview too (same code path), providing useful insight to the seller before publishing.
- **Sharing preview URL:** The preview route is inside `seller` middleware — buyers cannot access it.

---

### E. Implementation Steps
1. Add `preview()` method to `AuctionCrudController`.
2. Add route in `routes/web.php` inside seller group.
3. Update `auctions.show` Blade view to accept and respond to `$isPreview` flag.
4. Write feature test verifying buyers cannot access preview URLs.

---

### F. Complexity & Priority
- **Complexity:** Low
- **Priority:** MVP

---

## Shared Components Across General Auction Features

| Component | Used By Features |
|-----------|-----------------|
| `EscrowService::holdForBid()` | BIN (7), normal bidding |
| `PaymentService::captureWinnerPayment()` | BIN (7) |
| `AuctionClosed` event + `HandleAuctionClosed` listener | BIN (7), Re-listing (9) |
| `AuctionPolicy` gates | All |
| `AuctionCloneService` | Re-listing (9), Lot auction cloning |
| Spatie MediaLibrary | Lot items (14), Re-listing (9) |
| `AuditLog::record()` | All write operations |

## Architectural Improvements

1. **Introduce `AuctionResource`** (Feature 8 suggests this) as a single JSON serialisation point across `AuctionController::show()`, `liveState()`, and future API endpoints. This eliminates duplicate array-building logic.

2. **Extract `AuctionPublishValidator`** from `AuctionCrudController::publish()` into a standalone service — reusable for auto-save validation feedback (Feature 21) and preview (Feature 22).

3. **Quick wins:** Features 8 (one migration + accessor) and 22 (one method + route) can ship in a single PR.

4. **Complex rewrite:** Feature 14 (Lot Auctions) requires new model, controller, media collection, and touches clone/search — plan as a dedicated sprint.