# Product & Category Enhancements — Implementation Plan

> Codebase baseline: Laravel 11, PostgreSQL, Spatie MediaLibrary, Redis cache, `CategoryService`, `AttributeService`.

---

## Feature 90 — Product Authenticity Certificate Upload

### A. Feature Overview
Allow sellers to upload authenticity certificates (PDF, image) for high-value items (watches, jewellery, collectibles). Buyers see a verified badge and can download/view the certificate. Admins can mark certificates as verified.

**Business goal:** Increases buyer confidence; reduces fraud disputes; differentiates premium listings; required for luxury/collectible categories.

---

### B. Current State
- `Auction` model uses Spatie MediaLibrary with `images` and `cover` collections.
- No document upload feature exists.
- `AuditLog` tracks admin actions.
- `Attribute` model has `type: boolean` (e.g. "Authenticated") but stores no file.

---

### C. Required Changes

#### 1. Database

```php
// Migration: add authenticity fields to auctions
Schema::table('auctions', function (Blueprint $table) {
    $table->boolean('has_authenticity_cert')->default(false)->after('serial_number');
    $table->string('authenticity_cert_status', 20)->default('none')->after('has_authenticity_cert');
    // Values: 'none', 'uploaded', 'verified', 'rejected'
    $table->timestamp('authenticity_cert_verified_at')->nullable()->after('authenticity_cert_status');
    $table->foreignId('authenticity_cert_verified_by')->nullable()
          ->constrained('users')->nullOnDelete()
          ->after('authenticity_cert_verified_at');
    $table->text('authenticity_cert_notes')->nullable()->after('authenticity_cert_verified_by');

    $table->index(['has_authenticity_cert', 'authenticity_cert_status']);
});
```

Media documents stored via Spatie — no additional table needed. Use a new media collection `authenticity_cert`.

#### 2. Backend Logic

**`app/Models/Auction.php`** — add new media collection:

```php
// Fillable additions
'has_authenticity_cert', 'authenticity_cert_status',
'authenticity_cert_verified_at', 'authenticity_cert_verified_by', 'authenticity_cert_notes',

// Casts
'has_authenticity_cert'          => 'boolean',
'authenticity_cert_verified_at'  => 'datetime',
'authenticity_cert_verified_by'  => 'integer',

// In registerMediaCollections()
$this->addMediaCollection('authenticity_cert')
    ->singleFile()                   // Only one certificate per auction
    ->acceptsMimeTypes([
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ])
    ->useDisk('private');           // Private disk — access via signed URL only

// Helper
public function getAuthenticityBadgeAttribute(): ?string
{
    return match ($this->authenticity_cert_status) {
        'verified' => 'verified',
        'uploaded' => 'pending_verification',
        default    => null,
    };
}

public function hasVerifiedCertificate(): bool
{
    return $this->authenticity_cert_status === 'verified';
}
```

**`app/Http/Controllers/Seller/AuctionCrudController.php`** — add upload and delete methods:

```php
public function uploadAuthCert(Request $request, Auction $auction): JsonResponse
{
    $this->authorize('uploadMedia', $auction);

    $request->validate([
        'file' => [
            'required',
            'file',
            'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
            'max:10240', // 10 MB max
        ],
    ]);

    // Clear any existing certificate
    $auction->clearMediaCollection('authenticity_cert');

    $media = $auction->addMediaFromRequest('file')
        ->toMediaCollection('authenticity_cert');

    $auction->update([
        'has_authenticity_cert'      => true,
        'authenticity_cert_status'   => 'uploaded',
        'authenticity_cert_verified_at' => null,
        'authenticity_cert_verified_by' => null,
    ]);

    AuditLog::record('auction.auth_cert.uploaded', Auction::class, $auction->id, [
        'media_id' => $media->id,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Certificate uploaded. Our team will verify it shortly.',
    ]);
}

public function deleteAuthCert(Request $request, Auction $auction): JsonResponse
{
    $this->authorize('uploadMedia', $auction);

    $auction->clearMediaCollection('authenticity_cert');
    $auction->update([
        'has_authenticity_cert'      => false,
        'authenticity_cert_status'   => 'none',
        'authenticity_cert_verified_at' => null,
        'authenticity_cert_verified_by' => null,
    ]);

    return response()->json(['success' => true]);
}
```

**Secure certificate download** — generate a temporary signed URL for buyers to view:

```php
// app/Http/Controllers/AuctionCertificateController.php
public function download(Auction $auction)
{
    // Anyone who can view the auction can download (active or completed)
    if (! in_array($auction->status, [Auction::STATUS_ACTIVE, Auction::STATUS_COMPLETED])) {
        abort(403);
    }

    $media = $auction->getFirstMedia('authenticity_cert');
    abort_unless($media, 404, 'No certificate uploaded.');

    // Stream from private disk — do NOT serve a permanent URL
    return response()->file($media->getPath(), [
        'Content-Type'        => $media->mime_type,
        'Content-Disposition' => 'inline; filename="' . $media->file_name . '"',
    ]);
}
```

**Admin verification:**

```php
// app/Http/Controllers/Admin/AuctionManagementController.php — add methods
public function verifyCert(Request $request, Auction $auction): JsonResponse
{
    $request->validate([
        'status' => ['required', Rule::in(['verified', 'rejected'])],
        'notes'  => ['nullable', 'string', 'max:500'],
    ]);

    $auction->update([
        'authenticity_cert_status'      => $request->input('status'),
        'authenticity_cert_verified_at' => now(),
        'authenticity_cert_verified_by' => $request->user()->id,
        'authenticity_cert_notes'       => $request->input('notes'),
    ]);

    AuditLog::record('auction.auth_cert.' . $request->input('status'), Auction::class, $auction->id, [
        'notes' => $request->input('notes'),
    ]);

    // Notify seller
    $auction->seller?->notify(new \App\Notifications\AuthCertStatusNotification($auction));

    return response()->json(['message' => "Certificate marked as {$request->input('status')}."]); 
}
```

**`app/Notifications/AuthCertStatusNotification.php`** — new notification for seller:

```php
// Sends email + database notification to seller when admin verifies/rejects cert
// Standard notification structure following existing pattern
```

#### 3. API / Routes

```php
// Seller routes (inside seller middleware group)
Route::post('/auctions/{auction}/auth-cert',   [AuctionCrudController::class, 'uploadAuthCert'])->name('seller.auctions.auth-cert.upload');
Route::delete('/auctions/{auction}/auth-cert', [AuctionCrudController::class, 'deleteAuthCert'])->name('seller.auctions.auth-cert.delete');

// Public (authenticated)
Route::get('/auctions/{auction}/auth-cert',    [AuctionCertificateController::class, 'download'])
    ->name('auctions.auth-cert.download')->middleware('auth');

// Admin
Route::post('/admin/auctions/{auction}/auth-cert/verify', [AuctionManagementController::class, 'verifyCert'])
    ->name('admin.auctions.auth-cert.verify')->middleware(['auth', 'staff']);
```

#### 4. Frontend (contract)
- **Seller edit page:** "Upload Authenticity Certificate" file picker (PDF/image). Show current status badge: "Pending verification" / "Verified ✓" / "Rejected ✗".
- **Public auction detail:** Show "Authenticity Verified ✓" badge near the price if `authenticity_cert_status === 'verified'`. "View Certificate" button linking to download route.
- **Admin auction detail:** Show "Verify Certificate" / "Reject Certificate" buttons with notes field.
- **Search/Browse:** Filter "Authenticated items only" checkbox (queries `has_authenticity_cert = true AND authenticity_cert_status = 'verified'`).

#### 5. Integrations
- **Private disk:** Certificate files must NOT be in `public` storage. Use `local` (private) disk. The download controller streams the file with authentication check.
- **Category restriction (optional):** Can configure which categories require/allow certificates via `category_attribute` table — add a `supports_authenticity_cert` boolean to `categories`.

---

### D. Dependencies & Risks
- **Storage:** Private disk on the default `local` storage. For production, use `s3` private bucket.
- **File size:** 10 MB limit is generous for PDFs. Consider virus scanning for uploaded documents (integrate ClamAV via Laravel package in production).
- **Auto-verification:** For speed, consider auto-approving certs from trusted sellers (sellers with >50 completed auctions and 5-star rating) via a `trusted_seller` flag.

---

### E. Implementation Steps
1. Migration: add `has_authenticity_cert`, `authenticity_cert_status`, related columns to `auctions`.
2. Register `authenticity_cert` media collection in `Auction::registerMediaCollections()`.
3. Add helper methods to `Auction` model.
4. Create `uploadAuthCert()` and `deleteAuthCert()` in `AuctionCrudController`.
5. Create `AuctionCertificateController` with secure download.
6. Add `verifyCert()` to admin `AuctionManagementController`.
7. Create `AuthCertStatusNotification`.
8. Register all routes.
9. Write tests: upload, download (auth required), admin verify, seller notification.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 92 — Product Comparison Tool

### A. Feature Overview
Allow users to select up to 3–4 active auctions and view them side-by-side in a comparison table: images, price, condition, brand, attributes, bid count, time remaining.

**Business goal:** Improves decision-making; keeps users on-site longer; reduces "I'll think about it" abandonment.

---

### B. Current State
- `AuctionAttributeValue` + `Attribute` models exist and are loaded in `AuctionController::show()`.
- No comparison state management exists.
- `AuctionController::liveState()` returns a JSON snapshot — partially reusable.

---

### C. Required Changes

#### 1. Database
No new tables needed. Comparison is a client-side selection + a single API call.

#### 2. Backend Logic

**`app/Http/Controllers/AuctionComparisonController.php`** — new controller:

```php
namespace App\Http\Controllers;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionComparisonController extends Controller
{
    public function __construct(protected BiddingStrategy $engine) {}

    /**
     * Return structured comparison data for N auctions.
     * Max 4 auctions per request.
     */
    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:2', 'max:4'],
            'ids.*' => ['integer', 'exists:auctions,id'],
        ]);

        $auctions = Auction::whereIn('id', $validated['ids'])
            ->where('status', Auction::STATUS_ACTIVE)
            ->with([
                'brand',
                'categories',
                'media',
                'attributeValues.attribute',
            ])
            ->withCount('bids')
            ->get();

        // Collect all unique attributes across all auctions for column headers
        $allAttributeSlugs = $auctions->flatMap(fn ($a) => $a->attributeValues
            ->map(fn ($av) => [
                'slug' => $av->attribute->slug,
                'name' => $av->attribute->name,
            ])
        )->unique('slug')->values();

        $rows = $auctions->map(function (Auction $auction) use ($allAttributeSlugs) {
            $currentPrice = $this->engine->getCurrentPrice($auction);
            $attrMap = $auction->attributeValues->keyBy(fn ($av) => $av->attribute->slug);

            return [
                'id'            => $auction->id,
                'title'         => $auction->title,
                'current_price' => $currentPrice,
                'next_minimum'  => $auction->minimumNextBid(),
                'bid_count'     => $auction->bids_count,
                'time_remaining'=> $auction->timeRemaining(),
                'end_time'      => $auction->end_time->toIso8601String(),
                'condition'     => $auction->condition_label,
                'brand'         => $auction->brand?->name,
                'reserve_met'   => $auction->reserve_met,
                'thumbnail_url' => $auction->getCoverImageUrl('thumbnail'),
                'url'           => route('auctions.show', $auction),
                'attributes'    => $allAttributeSlugs->mapWithKeys(fn ($attr) => [
                    $attr['slug'] => $attrMap[$attr['slug']]?->value ?? null,
                ])->all(),
            ];
        });

        return response()->json([
            'attribute_columns' => $allAttributeSlugs,
            'auctions'          => $rows->values(),
        ]);
    }
}
```

#### 3. API / Routes

```php
// Public (no auth required for viewing active auctions)
Route::post('/auctions/compare', [AuctionComparisonController::class, 'compare'])
    ->name('auctions.compare');

// Optional: GET /auctions/compare?ids[]=1&ids[]=2
Route::get('/auctions/compare', [AuctionComparisonController::class, 'compare'])
    ->name('auctions.compare.get');
```

#### 4. Frontend (contract)
- **Compare checkbox** on each auction card (visible on hover or always visible on browse page).
- **Floating comparison bar** at bottom of screen showing selected items (1–4) with "Compare" button.
- **Comparison page** (`/auctions/compare?ids[]=1&ids[]=2...`): full-width table with sticky header row (attribute names) and columns per auction.
- **Real-time prices**: Poll `/auctions/{id}/live-state` every 30 seconds per compared auction, or subscribe to Reverb channels.
- Store selected comparison IDs in `localStorage` (client-side only — no server state needed).

---

### D. Dependencies & Risks
- **Cross-category comparisons:** Attribute columns will be sparse when comparing auctions from different categories (some attributes N/A). Display "—" for missing values.
- **Performance:** `withCount('bids')` + eager loading for 4 auctions is acceptable (4 simple queries). Cache result for 30 seconds if needed.

---

### E. Implementation Steps
1. Create `AuctionComparisonController` with `compare()` method.
2. Register routes.
3. Write API test verifying correct attribute alignment across auctions.
4. Frontend implementation: comparison bar + comparison page (deferred to frontend sprint).

---

### F. Complexity & Priority
- **Complexity:** Low–Medium (backend simple; frontend is the complex part)
- **Priority:** Growth

---

## Feature 96 — Category Commission Rates

### A. Feature Overview
Allow administrators to configure different platform commission rates per category. For example: Electronics → 8%, Collectibles → 5%, Vehicles → 3%. Overrides the global `auction.platform_fee_percent` config.

**Business goal:** Flexible monetisation; competitive pricing in high-value categories; category-specific margins.

---

### B. Current State
- `config/auction.php` has `platform_fee_percent` (single global rate, default 5%).
- `PaymentService::calculatePlatformFee()` reads only this global config.
- `Category` model has no commission field.
- `Invoice` stores `platform_fee` as a computed value at auction close.

---

### C. Required Changes

#### 1. Database

```php
// Migration: add commission rate to categories
Schema::table('categories', function (Blueprint $table) {
    $table->decimal('commission_rate', 5, 4)->nullable()->after('sort_order');
    // NULL = inherit from parent or fall back to global config
    // 0.05 = 5%, 0.08 = 8%
    $table->index('commission_rate');
});
```

No separate table needed — the nullable column with inheritance logic is simpler and avoids join complexity.

#### 2. Backend Logic

**`app/Models/Category.php`** — add:

```php
'commission_rate' => 'decimal:4', // cast
'commission_rate', // fillable

/**
 * Resolve effective commission rate, walking up the category tree.
 * Returns the first non-null rate found, or the global config default.
 */
public function getEffectiveCommissionRateAttribute(): float
{
    if ($this->commission_rate !== null) {
        return (float) $this->commission_rate;
    }

    // Walk up the ancestor chain
    foreach ($this->ancestors->reverse() as $ancestor) {
        if ($ancestor->commission_rate !== null) {
            return (float) $ancestor->commission_rate;
        }
    }

    // Fall back to global config
    return (float) config('auction.platform_fee_percent', 5.0) / 100;
}
```

**`app/Services/PaymentService.php`** — modify `calculatePlatformFee()`:

```php
public function calculatePlatformFee(float $amount, ?Auction $auction = null): float
{
    $rate = $this->resolveCommissionRate($auction);
    return round($amount * $rate, 2);
}

public function calculateSellerAmount(float $amount, ?Auction $auction = null): float
{
    return round($amount - $this->calculatePlatformFee($amount, $auction), 2);
}

private function resolveCommissionRate(?Auction $auction): float
{
    if (! $auction) {
        return (float) config('auction.platform_fee_percent', 5.0) / 100;
    }

    // Get primary category
    $primaryCategory = $auction->primaryCategory()->first();

    if ($primaryCategory) {
        return $primaryCategory->effective_commission_rate;
        // Note: effective_commission_rate already returns a decimal (0.05 = 5%)
        // but global config is stored as percentage (5.0 = 5%), so normalise:
    }

    return (float) config('auction.platform_fee_percent', 5.0) / 100;
}
```

> **Important:** Standardise commission storage as decimal (0.05 = 5%) throughout — do not mix percentage and decimal representations. Update `config/auction.php` accordingly and add a note.

**Update all callers of `calculatePlatformFee()`** — pass `$auction` when available:

In `HandleAuctionClosed::capturePayment()`:
```php
$platformFee  = $this->paymentService->calculatePlatformFee($amount, $auction);
$sellerAmount = $this->paymentService->calculateSellerAmount($amount, $auction);
```

**`app/Http/Controllers/Admin/CategoryController.php`** — extend `update()`:

```php
// Add to validation
'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],

// Add to update
'commission_rate' => $validated['commission_rate'] ?? $category->commission_rate,
```

**Admin category UI** — show effective commission rate (resolved with inheritance) alongside the override field.

**`app/Services/CategoryService.php`** — add helper:

```php
public function getEffectiveCommissionRate(int $categoryId): float
{
    $category = Category::find($categoryId);
    return $category?->effective_commission_rate ?? ((float) config('auction.platform_fee_percent', 5.0) / 100);
}
```

#### 3. API / Routes
No new routes — extends existing admin category update endpoint and `PaymentService`.

Add to seller auction creation page — show "Platform commission for this category: X%" as informational text when seller selects a category.

```php
// New read-only endpoint
Route::get('/api/categories/{id}/commission', function (int $id) {
    $rate = app(CategoryService::class)->getEffectiveCommissionRate($id);
    return response()->json(['commission_rate' => $rate, 'commission_pct' => round($rate * 100, 2)]);
});
```

#### 4. Frontend (contract)
- **Admin Category Edit:** "Commission Rate Override (%)" field, blank = inherit from parent/global.
- **Seller Auction Create:** After selecting a category, fetch and display "Platform commission: X% of winning bid".
- **Admin Auction Detail:** Show actual commission rate used at close (stored in `Invoice::platform_fee` relative to total).

---

### D. Dependencies & Risks
- **Ancestor traversal:** `Category::getAncestorsAttribute()` uses `path` for efficient lookup — no N+1 issue.
- **Existing invoices:** Commission rate change only affects future auctions — historical `Invoice.platform_fee` values are immutable.
- **Config normalisation:** The global config uses percentage (5.0 for 5%). The database column should store decimal (0.05 for 5%). Ensure `getEffectiveCommissionRateAttribute()` returns a decimal consistently and callers don't double-divide.

---

### E. Implementation Steps
1. Migration: add `commission_rate` (nullable decimal) to `categories`.
2. Update `Category` model — fillable, cast, `getEffectiveCommissionRateAttribute()`.
3. Update `PaymentService::calculatePlatformFee()` and `calculateSellerAmount()` to accept optional `Auction`.
4. Update all callers (`HandleAuctionClosed`, admin controllers).
5. Update `AdminCategoryController::update()` validation and persistence.
6. Add `/api/categories/{id}/commission` endpoint.
7. Normalise config representation (document the decimal vs percentage convention).
8. Write tests: flat rate override, inheritance from parent, fallback to global.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 97 — Featured Categories on Homepage

### A. Feature Overview
Administrators designate certain categories as "featured" — they appear prominently on the homepage with custom banners, icons, and descriptions. Featured categories drive buyers to curated sections.

**Business goal:** Merchandising control; promotes high-margin categories; seasonal promotions.

---

### B. Current State
- `Category` model exists with `is_active`, `icon`, `image_path`, `sort_order`.
- `WarmCache` command warms `root_categories` and `featured_auctions` caches.
- Homepage route in `routes/web.php` loads `featured_auctions` from cache but has no featured *categories*.
- `CategoryService::getRootWithAuctionCounts()` returns all active root categories.

---

### C. Required Changes

#### 1. Database

```php
// Migration: add featured fields to categories
Schema::table('categories', function (Blueprint $table) {
    $table->boolean('is_featured')->default(false)->after('is_active');
    $table->timestamp('featured_until')->nullable()->after('is_featured');
    $table->unsignedInteger('featured_sort_order')->default(0)->after('featured_until');
    $table->string('featured_banner_path')->nullable()->after('featured_sort_order');
    // Custom banner image for featured homepage display (different from category list icon)
    $table->string('featured_tagline', 200)->nullable()->after('featured_banner_path');
    // Short marketing copy: "Shop the latest tech deals"

    $table->index(['is_featured', 'featured_sort_order']);
});
```

#### 2. Backend Logic

**`app/Models/Category.php`** — add:

```php
// Fillable additions
'is_featured', 'featured_until', 'featured_sort_order',
'featured_banner_path', 'featured_tagline',

// Casts additions
'is_featured'          => 'boolean',
'featured_until'       => 'datetime',
'featured_sort_order'  => 'integer',

// Scope
public function scopeFeatured(Builder $query): Builder
{
    return $query->where('is_featured', true)
        ->where(function (Builder $q) {
            $q->whereNull('featured_until')->orWhere('featured_until', '>', now());
        })
        ->orderBy('featured_sort_order');
}

public function getIsCurrentlyFeaturedAttribute(): bool
{
    return $this->is_featured
        && ($this->featured_until === null || $this->featured_until->isFuture());
}
```

**`app/Services/CategoryService.php`** — add method:

```php
private const FEATURED_CACHE_KEY = 'categories:featured:v1';
private const FEATURED_CACHE_TTL = 300; // 5 minutes

public function getFeaturedCategories(): Collection
{
    return Cache::remember(self::FEATURED_CACHE_KEY, self::FEATURED_CACHE_TTL, function () {
        return Category::featured()
            ->active()
            ->with('children')
            ->withCount(['auctions' => function ($query) {
                $query->where('status', 'active')->where('end_time', '>', now());
            }])
            ->get();
    });
}

public function invalidateCache(): void
{
    // Add to existing invalidateCache() method:
    Cache::forget(self::FEATURED_CACHE_KEY);
    // ... existing cache forgets ...
}
```

**Homepage route** — update `routes/web.php`:

```php
Route::get('/', function () {
    $liveCount = Cache::remember('live_auction_count', 60, fn() =>
        Auction::where('status','active')->count()
    );
    $featuredAuctions = Cache::remember('featured_auctions', 300, fn() =>
        Auction::featured()->with('media')->take(8)->get()
    );
    $endingSoonAuctions = Cache::remember('ending_soon_auctions', 60, fn() =>
        Auction::active()->where('end_time', '<=', now()->addHours(6))->orderBy('end_time')->take(8)->get()
    );
    $featuredCategories = app(CategoryService::class)->getFeaturedCategories();

    return view('welcome', compact(
        'liveCount', 'featuredAuctions', 'endingSoonAuctions', 'featuredCategories'
    ));
});
```

**`app/Http/Controllers/Admin/CategoryController.php`** — add `feature()` and `unfeature()` actions:

```php
public function feature(Request $request, Category $category): JsonResponse
{
    $validated = $request->validate([
        'duration_hours'       => ['required', 'integer', 'min:1', 'max:8760'],
        'featured_sort_order'  => ['nullable', 'integer', 'min:0'],
        'featured_tagline'     => ['nullable', 'string', 'max:200'],
    ]);

    $featuredUntil = now()->addHours((int) $validated['duration_hours']);

    $category->update([
        'is_featured'           => true,
        'featured_until'        => $featuredUntil,
        'featured_sort_order'   => $validated['featured_sort_order'] ?? 0,
        'featured_tagline'      => $validated['featured_tagline'] ?? null,
    ]);

    $this->categoryService->invalidateCache();

    AuditLog::record('category.featured', 'category', $category->id, [
        'featured_until'     => $featuredUntil->toIso8601String(),
        'featured_sort_order'=> $validated['featured_sort_order'] ?? 0,
    ]);

    return response()->json([
        'success' => true,
        'message' => "Category featured until {$featuredUntil->format('M d, Y H:i')}.",
    ]);
}

public function unfeature(Category $category): JsonResponse
{
    $category->update(['is_featured' => false, 'featured_until' => null]);
    $this->categoryService->invalidateCache();

    return response()->json(['success' => true, 'message' => 'Featured status removed.']);
}

public function uploadFeaturedBanner(Request $request, Category $category): JsonResponse
{
    $request->validate([
        'banner' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
    ]);

    $path = $request->file('banner')->store('categories/banners', 'public');
    $category->update(['featured_banner_path' => $path]);
    $this->categoryService->invalidateCache();

    return response()->json(['success' => true, 'banner_url' => asset('storage/' . $path)]);
}
```

**`app/Console/Commands/WarmCache.php`** — add featured categories to warmers:

```php
'featured_categories' => fn () => Cache::remember(
    'categories:featured:v1', 300,
    fn () => app(CategoryService::class)->getFeaturedCategories()
),
```

**Scheduled auto-expire** — add to `routes/console.php`:

```php
// Unfeature expired categories
Schedule::call(function () {
    Category::where('is_featured', true)
        ->whereNotNull('featured_until')
        ->where('featured_until', '<=', now())
        ->update(['is_featured' => false]);

    app(CategoryService::class)->invalidateCache();
})->hourly()->name('unfeature-expired-categories');
```

#### 3. API / Routes

```php
// Admin routes (inside admin + staff middleware group)
Route::post('/admin/categories/{category}/feature',         [AdminCategoryController::class, 'feature'])->name('admin.categories.feature');
Route::delete('/admin/categories/{category}/feature',       [AdminCategoryController::class, 'unfeature'])->name('admin.categories.unfeature');
Route::post('/admin/categories/{category}/featured-banner', [AdminCategoryController::class, 'uploadFeaturedBanner'])->name('admin.categories.featured-banner');
```

#### 4. Frontend (contract)
- **Homepage:** Featured categories section above the regular browse grid. Each featured category card shows: banner image, name, tagline, live auction count, "Shop Now" CTA.
- **Admin Categories List:** "Feature" button on each category row → modal with duration and tagline fields.
- **Featured categories:** Displayed with badge in category browse page.

---

### D. Dependencies & Risks
- **Cache invalidation:** `invalidateCache()` in `CategoryService` already clears `root_categories` — extend to clear `featured_categories` key too.
- **Auto-expire:** The hourly scheduler is sufficient for featured category expiry — this is not a real-time operation.
- **Banner storage:** Stored in `public` disk (accessible via `/storage/`). Add banner to `categoryService->invalidateCache()` to prevent stale CDN caches in production.

---

### E. Implementation Steps
1. Migration: add `is_featured`, `featured_until`, `featured_sort_order`, `featured_banner_path`, `featured_tagline` to `categories`.
2. Update `Category` model — fillable, casts, `scopeFeatured()`, `getIsCurrentlyFeaturedAttribute()`.
3. Add `getFeaturedCategories()` to `CategoryService`.
4. Update `CategoryService::invalidateCache()` to clear featured cache key.
5. Update homepage route to pass `$featuredCategories`.
6. Add `feature()`, `unfeature()`, `uploadFeaturedBanner()` to `AdminCategoryController`.
7. Register new admin routes.
8. Add `featured_categories` to `WarmCache` command warmers.
9. Add hourly scheduler to auto-expire featured categories.
10. Write tests: featured scope query, cache invalidation, auto-expire.

---

### F. Complexity & Priority
- **Complexity:** Low
- **Priority:** Growth (immediate visual impact on homepage)

---

## Shared Components Across Product Features

| Component | Used By Features |
|-----------|-----------------|
| Spatie MediaLibrary | Auth Certificate (90), Featured Banner (97) |
| `CategoryService::invalidateCache()` | Category Commission (96), Featured (97) |
| `AuditLog::record()` | All admin write operations |
| `WarmCache` command | Featured Categories (97) |
| `PaymentService` | Category Commission (96) |
| `Category::ancestors` accessor | Category Commission (96) |

## Quick Wins (≤ 1 day)
- **Feature 92** (Comparison): 1 controller + 1 route — no DB changes. Completable in half a day.
- **Feature 97** (Featured Categories): 1 migration + model additions + existing pattern (mirrors `Auction::scopeFeatured()`).

## Architectural Notes
1. **Commission Rate Normalisation:** Before implementing Feature 96, convert `config/auction.php` `platform_fee_percent` to decimal format (`0.05` instead of `5.0`) to establish a single convention across codebase.
2. **Media Strategy:** Features 90 and 97 both add media to existing Spatie-managed models. Consider creating a `HasFeaturedMedia` trait for consistent `featured_banner` handling.
3. **Category Model Bloat:** The `Category` model is growing (depth/path, is_active, is_featured, commission_rate). Consider extracting `CategoryMetaData` as a separate related model to keep `Category` lean — though this adds complexity and may be premature.