# Seller Tools — Implementation Plan

> Codebase baseline: Laravel 12, PostgreSQL, Redis, Stripe Connect, Horizon queues.

---

## Feature 67 — Seller Listing Fee

### A. Feature Overview
Charge sellers a flat fee (or tiered fee) when they publish an auction, regardless of whether it sells. This is separate from the existing platform commission (`auction.platform_fee_percent`) which is only charged on successful sales.

**Business goal:** Creates a sustainable revenue floor; discourages low-quality spam listings; common in marketplaces (eBay insertion fee model).

---

### B. Current State
- `config/auction.php` has `platform_fee_percent` (success commission only).
- `PaymentService::calculatePlatformFee()` only runs at auction close (`captureWinnerPayment`).
- `WalletService` supports `withdraw()` and `hold()`.
- `AuctionCrudController::publish()` validates and publishes but deducts nothing.
- `AuditLog::record()` tracks `auction.published` actions.

---

### C. Required Changes

#### 1. Database

```php
// Migration: add_listing_fee_to_auctions_table
Schema::table('auctions', function (Blueprint $table) {
    $table->decimal('listing_fee_charged', 10, 2)->default(0.00)->after('payment_status');
    $table->boolean('listing_fee_paid')->default(false)->after('listing_fee_charged');
});

// Migration: create_listing_fee_tiers_table (for tiered/category-based fees)
Schema::create('listing_fee_tiers', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->decimal('starting_price_min', 15, 2)->nullable(); // NULL = no lower bound
    $table->decimal('starting_price_max', 15, 2)->nullable(); // NULL = no upper bound
    $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
    $table->decimal('fee_amount', 10, 2)->default(0.00); // flat fee
    $table->decimal('fee_percent', 5, 4)->default(0.00); // percentage of starting_price
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();

    $table->index(['is_active', 'sort_order']);
});
```

#### 2. Backend Logic

**`app/Services/ListingFeeService.php`** — new service:

```php
namespace App\Services;

use App\Models\Auction;
use App\Models\ListingFeeTier;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListingFeeService
{
    public function __construct(protected WalletService $walletService) {}

    /**
     * Calculate the listing fee for an auction before charging.
     */
    public function calculate(Auction $auction): float
    {
        // Check config-level global fee first
        $globalFlat    = (float) config('auction.listing_fee.flat', 0.0);
        $globalPercent = (float) config('auction.listing_fee.percent', 0.0);

        // Look for a matching tier (most specific wins)
        $tier = $this->resolveTier($auction);

        if ($tier) {
            $flat    = (float) $tier->fee_amount;
            $percent = (float) $tier->fee_percent;
        } else {
            $flat    = $globalFlat;
            $percent = $globalPercent;
        }

        $startingPrice = (float) $auction->starting_price;
        $fee = $flat + ($startingPrice * $percent);

        return round($fee, 2);
    }

    /**
     * Charge the listing fee by debiting the seller's wallet.
     * Called inside AuctionCrudController::publish() transaction.
     */
    public function charge(Auction $auction, User $seller): WalletTransaction
    {
        $fee = $this->calculate($auction);

        if ($fee <= 0) {
            return new WalletTransaction(); // No fee — return empty transaction
        }

        if (! $seller->canAfford($fee)) {
            throw new \DomainException(
                "Insufficient wallet balance to pay the listing fee of \${$fee}. "
                . "Available: \$" . $seller->availableBalance()
            );
        }

        $tx = $this->walletService->withdraw(
            $seller,
            $fee,
            "Listing fee for auction: {$auction->title}",
        );

        $auction->update([
            'listing_fee_charged' => $fee,
            'listing_fee_paid'    => true,
        ]);

        Log::info('ListingFeeService: fee charged', [
            'auction_id' => $auction->id,
            'seller_id'  => $seller->id,
            'fee'        => $fee,
        ]);

        return $tx;
    }

    private function resolveTier(Auction $auction): ?ListingFeeTier
    {
        $categoryId    = $auction->categories()->wherePivot('is_primary', true)->value('categories.id');
        $startingPrice = (float) $auction->starting_price;

        return ListingFeeTier::where('is_active', true)
            ->where(function ($q) use ($categoryId) {
                $q->whereNull('category_id')->orWhere('category_id', $categoryId);
            })
            ->where(function ($q) use ($startingPrice) {
                $q->whereNull('starting_price_min')->orWhere('starting_price_min', '<=', $startingPrice);
            })
            ->where(function ($q) use ($startingPrice) {
                $q->whereNull('starting_price_max')->orWhere('starting_price_max', '>', $startingPrice);
            })
            ->orderByDesc('category_id') // category-specific tier wins over global tier
            ->orderBy('sort_order')
            ->first();
    }
}
```

**`app/Models/ListingFeeTier.php`** — new model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingFeeTier extends Model
{
    protected $fillable = [
        'name', 'starting_price_min', 'starting_price_max',
        'category_id', 'fee_amount', 'fee_percent', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'starting_price_min' => 'decimal:2',
        'starting_price_max' => 'decimal:2',
        'fee_amount'         => 'decimal:2',
        'fee_percent'        => 'decimal:4',
        'is_active'          => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

**`app/Http/Controllers/Seller/AuctionCrudController.php`** — modify `publish()`:

```php
public function publish(Request $request, Auction $auction): RedirectResponse
{
    $this->authorize('publish', $auction);

    // ... existing missing-fields validation ...

    DB::transaction(function () use ($auction, $request) {
        // Charge listing fee FIRST (throws if insufficient balance)
        app(ListingFeeService::class)->charge($auction, $request->user());

        if ($auction->start_time === null) {
            $auction->start_time = now();
        }
        $auction->status        = Auction::STATUS_ACTIVE;
        $auction->current_price = $auction->starting_price;
        $auction->save();

        $this->biddingStrategy->initializePrice($auction);

        AuditLog::record('auction.published', Auction::class, $auction->id);
    });

    return redirect()->route('auctions.show', $auction)
        ->with('status', 'Auction published successfully.');
}
```

**Listing fee preview endpoint** — sellers should see the fee before committing:

```php
// AuctionCrudController or new endpoint
public function listingFeePreview(Auction $auction): JsonResponse
{
    $this->authorize('update', $auction);
    $fee = app(ListingFeeService::class)->calculate($auction);
    return response()->json(['listing_fee' => $fee]);
}
```

**`config/auction.php`** — add:

```php
'listing_fee' => [
    'flat'    => (float) env('AUCTION_LISTING_FEE_FLAT', 0.0),
    'percent' => (float) env('AUCTION_LISTING_FEE_PERCENT', 0.0),
],
```

**Admin CRUD for `ListingFeeTier`** — new controller at `app/Http/Controllers/Admin/ListingFeeController.php` with standard index/store/update/destroy actions. Register under `/admin/listing-fees` with `staff` middleware.

#### 3. API / Routes

```php
// Seller routes
Route::get('/auctions/{auction}/listing-fee-preview', [AuctionCrudController::class, 'listingFeePreview'])
    ->name('seller.auctions.listing-fee-preview');

// Admin routes (inside admin group)
Route::resource('listing-fees', ListingFeeController::class)->names('admin.listing-fees');
```

#### 4. Frontend (contract)
- On the publish confirmation dialog: show "Listing fee: $X.XX will be deducted from your wallet" before the seller clicks Publish.
- If wallet balance < fee, show error and link to top-up wallet.
- Admin panel: CRUD interface for listing fee tiers (manage price ranges and category-specific fees).

#### 5. Integrations
- `WalletService::withdraw()` is the payment mechanism — no Stripe involved (wallet-only deduction).
- Consider refunding listing fee if auction is cancelled within a grace period (24 hours). Add `listing_fee_refund_eligible_until` timestamp and a refund action in `AuctionCrudController::cancel()`.

---

### D. Dependencies & Risks
- **Zero-fee default:** Setting both `flat=0` and `percent=0` in config makes this a no-op — safe for platforms that don't want listing fees yet.
- **Insufficient balance:** If the seller has no wallet balance, `publish()` should fail gracefully with a redirect back, not a 500.
- **Free listing promotions:** Future promotions can be handled by creating a `ListingFeeTier` with `fee_amount=0` for a specific category or date range.

---

### E. Implementation Steps
1. Migration: add `listing_fee_charged`, `listing_fee_paid` to `auctions`.
2. Migration: create `listing_fee_tiers` table.
3. Create `ListingFeeTier` model.
4. Create `ListingFeeService` with `calculate()` and `charge()`.
5. Modify `AuctionCrudController::publish()` to call `ListingFeeService::charge()` inside transaction.
6. Add `listingFeePreview()` endpoint.
7. Add config keys to `config/auction.php`.
8. Create admin `ListingFeeController` and register routes.
9. Write feature tests covering: zero fee, flat fee, percentage fee, category-specific tier, insufficient balance rejection.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 73 — Seller Tax Document Generation

### A. Feature Overview
Generate downloadable tax documents (e.g., annual revenue summary, per-transaction records) for sellers to assist with tax filing. Format: PDF or CSV. Content includes: platform fees paid, gross sales, payout amounts, refunds.

**Business goal:** Regulatory compliance; reduces seller support tickets asking for sales history; required for professional sellers.

---

### B. Current State
- `InvoiceService::generatePdf()` generates per-transaction PDFs (uses DomPDF).
- `RevenueController::export()` exports a CSV of completed auctions (winner, amount, date).
- No annual/period tax summary document exists.
- `WalletTransaction` records all financial movements with `type`, `amount`, `description`.

---

### C. Required Changes

#### 1. Database
No new tables. Tax documents are generated on-demand from existing data.

Optional: cache generated PDFs:

```php
Schema::create('tax_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('period_label', 20); // e.g., "2025", "2025-Q1", "2025-01"
    $table->string('period_type', 10);  // 'annual', 'quarterly', 'monthly'
    $table->date('period_start');
    $table->date('period_end');
    $table->string('file_path')->nullable();
    $table->decimal('gross_sales', 15, 2)->default(0);
    $table->decimal('platform_fees_paid', 15, 2)->default(0);
    $table->decimal('listing_fees_paid', 15, 2)->default(0);
    $table->decimal('net_revenue', 15, 2)->default(0);
    $table->decimal('refunds_issued', 15, 2)->default(0);
    $table->timestamps();

    $table->unique(['user_id', 'period_label', 'period_type']);
    $table->index(['user_id', 'period_start']);
});
```

#### 2. Backend Logic

**`app/Services/TaxDocumentService.php`** — new service:

```php
namespace App\Services;

use App\Models\Auction;
use App\Models\TaxDocument;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TaxDocumentService
{
    /**
     * Compile tax summary data for a seller over a date range.
     */
    public function compileSummary(User $seller, Carbon $from, Carbon $to): array
    {
        // Gross sales: sum of winning_bid_amount for completed auctions in period
        $grossSales = Auction::where('user_id', $seller->id)
            ->where('status', Auction::STATUS_COMPLETED)
            ->whereBetween('closed_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('winning_bid_amount');

        // Platform commissions: sum of platform_fee from invoices
        $platformFees = \App\Models\Invoice::where('seller_id', $seller->id)
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('platform_fee');

        // Listing fees paid
        $listingFees = WalletTransaction::where('user_id', $seller->id)
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL)
            ->where('description', 'like', 'Listing fee%')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('amount');

        // Refunds issued (seller wallet debited for refund reversals)
        $refunds = WalletTransaction::where('user_id', $seller->id)
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL)
            ->where('description', 'like', 'Payout reversed%')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('amount');

        // Net revenue = gross - platform fees - listing fees - refunds
        $netRevenue = round(
            (float) $grossSales - (float) $platformFees - (float) $listingFees - (float) $refunds,
            2,
        );

        // Line items: each completed auction
        $lineItems = Auction::where('user_id', $seller->id)
            ->where('status', Auction::STATUS_COMPLETED)
            ->whereBetween('closed_at', [$from, $to])
            ->with('invoice')
            ->orderBy('closed_at')
            ->get(['id', 'title', 'winning_bid_amount', 'closed_at'])
            ->map(fn ($a) => [
                'auction_id'          => $a->id,
                'title'               => $a->title,
                'gross'               => (float) $a->winning_bid_amount,
                'platform_fee'        => (float) ($a->invoice?->platform_fee ?? 0),
                'net'                 => (float) ($a->invoice?->seller_amount ?? 0),
                'date'                => $a->closed_at?->toDateString(),
                'invoice_number'      => $a->invoice?->invoice_number,
            ])
            ->all();

        return [
            'seller_name'       => $seller->name,
            'seller_email'      => $seller->email,
            'period_from'       => $from->toDateString(),
            'period_to'         => $to->toDateString(),
            'gross_sales'       => round((float) $grossSales, 2),
            'platform_fees'     => round((float) $platformFees, 2),
            'listing_fees'      => round((float) $listingFees, 2),
            'refunds_issued'    => round((float) $refunds, 2),
            'net_revenue'       => $netRevenue,
            'line_items'        => $lineItems,
            'generated_at'      => now()->toDateTimeString(),
        ];
    }

    /**
     * Generate and cache a PDF tax document.
     */
    public function generatePdf(User $seller, Carbon $from, Carbon $to, string $label): string
    {
        $data = $this->compileSummary($seller, $from, $to);

        $html = view('tax-documents.summary', $data)->render();

        $directory = storage_path('app/tax-documents/' . $seller->id);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "tax-summary-{$label}.pdf";
        $path     = "tax-documents/{$seller->id}/{$filename}";
        $fullPath = storage_path("app/{$path}");

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tax-documents.summary', $data);
            $pdf->save($fullPath);
        } else {
            file_put_contents($fullPath . '.html', $html);
            $path .= '.html';
        }

        Log::info('TaxDocumentService: generated', [
            'seller_id' => $seller->id,
            'period'    => $label,
            'path'      => $path,
        ]);

        return $path;
    }
}
```

**`app/Models/TaxDocument.php`** — new model (standard Eloquent, matches migration above).

**`app/Http/Controllers/Seller/TaxDocumentController.php`** — new controller:

```php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Services\TaxDocumentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaxDocumentController extends Controller
{
    public function __construct(protected TaxDocumentService $service) {}

    public function index(Request $request)
    {
        $seller    = $request->user();
        $documents = TaxDocument::where('user_id', $seller->id)
            ->orderByDesc('period_start')
            ->paginate(20);

        // Build available years/quarters from closed auctions
        $availableYears = \App\Models\Auction::where('user_id', $seller->id)
            ->where('status', 'completed')
            ->whereNotNull('closed_at')
            ->selectRaw('EXTRACT(YEAR FROM closed_at)::int AS year')
            ->distinct()
            ->pluck('year');

        return view('seller.tax-documents.index', compact('documents', 'availableYears'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'period_type'  => ['required', 'in:annual,quarterly,monthly'],
            'year'         => ['required', 'integer', 'min:2020', 'max:' . now()->year],
            'quarter'      => ['nullable', 'integer', 'between:1,4'],
            'month'        => ['nullable', 'integer', 'between:1,12'],
        ]);

        $seller = $request->user();

        [$from, $to, $label] = $this->resolvePeriod($validated);

        // Upsert tax document record
        $doc = TaxDocument::firstOrNew([
            'user_id'      => $seller->id,
            'period_label' => $label,
            'period_type'  => $validated['period_type'],
        ]);

        $summary = $this->service->compileSummary($seller, $from, $to);
        $path    = $this->service->generatePdf($seller, $from, $to, $label);

        $doc->fill([
            'period_start'       => $from->toDateString(),
            'period_end'         => $to->toDateString(),
            'file_path'          => $path,
            'gross_sales'        => $summary['gross_sales'],
            'platform_fees_paid' => $summary['platform_fees'],
            'listing_fees_paid'  => $summary['listing_fees'],
            'net_revenue'        => $summary['net_revenue'],
            'refunds_issued'     => $summary['refunds_issued'],
        ])->save();

        return redirect()->route('seller.tax-documents.download', $doc)
            ->with('status', 'Tax document generated.');
    }

    public function download(TaxDocument $document)
    {
        abort_unless($document->user_id === request()->user()->id, 403);
        $fullPath = storage_path("app/{$document->file_path}");
        abort_unless(file_exists($fullPath), 404);
        return response()->download($fullPath);
    }

    private function resolvePeriod(array $v): array
    {
        $year = (int) $v['year'];

        return match ($v['period_type']) {
            'annual' => [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
                (string) $year,
            ],
            'quarterly' => [
                Carbon::create($year, ($v['quarter'] - 1) * 3 + 1, 1)->startOfDay(),
                Carbon::create($year, $v['quarter'] * 3, 1)->endOfMonth()->endOfDay(),
                "{$year}-Q{$v['quarter']}",
            ],
            'monthly' => [
                Carbon::create($year, $v['month'], 1)->startOfDay(),
                Carbon::create($year, $v['month'], 1)->endOfMonth()->endOfDay(),
                sprintf('%d-%02d', $year, $v['month']),
            ],
        };
    }
}
```

**Blade view** — create `resources/views/tax-documents/summary.blade.php` with professional layout including company header, seller info, period, line items table, totals.

#### 3. API / Routes

```php
Route::prefix('/tax-documents')->name('seller.tax-documents.')->group(function () {
    Route::get('/',                    [TaxDocumentController::class, 'index'])->name('index');
    Route::post('/generate',           [TaxDocumentController::class, 'generate'])->name('generate');
    Route::get('/{document}/download', [TaxDocumentController::class, 'download'])->name('download');
});
```

#### 4. Frontend (contract)
- Page: "Tax Documents" in seller dashboard sidebar.
- Dropdown selectors: Year, Period Type (Annual/Quarterly/Monthly).
- "Generate Report" button → POST, redirect to download.
- Table of previously generated documents with download links.

---

### D. Dependencies & Risks
- **DomPDF:** Already used in `InvoiceService`. If not installed, falls back to HTML (acceptable).
- **Data accuracy:** Tax data comes from `WalletTransaction` and `Invoice` records — both have correct amounts if `PaymentService` runs correctly.
- **GDPR:** Tax documents contain seller PII — store in private (non-public) disk, access only via authenticated download route.

---

### E. Implementation Steps
1. Migration: create `tax_documents` table.
2. Create `TaxDocument` model.
3. Create `TaxDocumentService` with `compileSummary()` and `generatePdf()`.
4. Create Blade view `tax-documents/summary.blade.php`.
5. Create `TaxDocumentController`.
6. Register routes inside seller middleware group.
7. Write integration test covering annual summary calculation accuracy.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Feature 74 — Seller Return / Refund Policy Setting

### A. Feature Overview
Allow sellers to define and display their return/refund policy on their storefront and individual auction pages. Buyers see the policy before bidding. Admins can view policy when resolving disputes.

**Business goal:** Reduces dispute volume by setting clear buyer expectations; builds trust; required for compliance in many jurisdictions.

---

### B. Current State
- `User` model has `seller_bio` (storefront text) but no structured return policy field.
- `DisputeController` and `AdminDisputeController` handle disputes but have no policy reference.
- No `return_policy` concept exists anywhere in the codebase.

---

### C. Required Changes

#### 1. Database

```php
// Migration: add_return_policy_to_users_table
Schema::table('users', function (Blueprint $table) {
    $table->string('return_policy_type', 30)->default('no_returns')->after('seller_bio');
    // Values: 'no_returns', 'returns_accepted', 'custom'
    $table->unsignedInteger('return_window_days')->nullable()->after('return_policy_type');
    $table->text('return_policy_custom')->nullable()->after('return_window_days');
});

// Per-auction override (seller can override global policy for specific auctions)
Schema::table('auctions', function (Blueprint $table) {
    $table->string('return_policy_override', 30)->nullable()->after('buy_it_now_enabled');
    // NULL = inherit from seller's global policy
    $table->text('return_policy_custom_override')->nullable()->after('return_policy_override');
});
```

#### 2. Backend Logic

**`app/Models/User.php`** — add:

```php
// Fillable
'return_policy_type', 'return_window_days', 'return_policy_custom',

// Casts
'return_window_days' => 'integer',

// Helper
public function getReturnPolicyLabelAttribute(): string
{
    return match ($this->return_policy_type) {
        'returns_accepted' => "Returns accepted within {$this->return_window_days} days",
        'custom'           => $this->return_policy_custom ?? 'See custom policy',
        default            => 'No returns accepted',
    };
}
```

**`app/Http/Requests/UpdateStorefrontRequest.php`** — add:

```php
'return_policy_type'   => ['required', Rule::in(['no_returns', 'returns_accepted', 'custom'])],
'return_window_days'   => ['nullable', 'required_if:return_policy_type,returns_accepted', 'integer', 'min:1', 'max:90'],
'return_policy_custom' => ['nullable', 'required_if:return_policy_type,custom', 'string', 'max:2000'],
```

**`app/Http/Controllers/Seller/StorefrontController::update()`** — add the new fields to `$data`:

```php
$data['return_policy_type']   = $request->input('return_policy_type');
$data['return_window_days']   = $request->input('return_window_days');
$data['return_policy_custom'] = $request->input('return_policy_custom');
```

**Per-auction policy override** — add to `StoreAuctionRequest` / `UpdateAuctionRequest`:

```php
'return_policy_override'        => ['nullable', Rule::in(['no_returns', 'returns_accepted', 'custom'])],
'return_policy_custom_override' => ['nullable', 'string', 'max:2000'],
```

**`app/Models/Auction.php`** — add helper for effective policy:

```php
public function getEffectiveReturnPolicyAttribute(): string
{
    if ($this->return_policy_override) {
        return match ($this->return_policy_override) {
            'returns_accepted' => "Returns accepted (see auction details)",
            'custom'           => $this->return_policy_custom_override ?? 'See custom policy',
            default            => 'No returns accepted',
        };
    }
    return $this->seller?->return_policy_label ?? 'No returns accepted';
}
```

#### 3. API / Routes
No new routes — extends existing `UpdateStorefrontRequest` and storefront update endpoint.

Expose in `AuctionController::show()`:

```php
// Add to the data passed to view
$returnPolicy = $auction->effective_return_policy;
```

#### 4. Frontend (contract)
- **Storefront edit page:** Section "Return Policy" with radio buttons (No Returns / Returns Accepted / Custom). Conditional fields for window days and custom text.
- **Auction detail page:** Display return policy in a collapsible "Seller Policy" section near the bid form.
- **Dispute creation form:** Pre-fill "not_as_described" dispute type with a note showing the seller's stated policy.

---

### D. Dependencies & Risks
- **Dispute integration:** `AdminDisputeController::show()` should load and display the effective return policy for the disputed auction.
- **Policy change after publish:** If a seller changes their return policy after an auction is active, the *auction's* effective policy should be snapshotted at publish time to prevent retroactive changes.
  - Solution: store `effective_return_policy_snapshot` text on `auctions` table at publish time.

```php
// Additional migration
$table->text('effective_return_policy_snapshot')->nullable();

// In AuctionCrudController::publish()
$auction->update(['effective_return_policy_snapshot' => $auction->effective_return_policy]);
```

---

### E. Implementation Steps
1. Migration: add `return_policy_*` columns to `users`.
2. Migration: add `return_policy_override*` columns and `effective_return_policy_snapshot` to `auctions`.
3. Update `User` model — fillable, casts, `getReturnPolicyLabelAttribute()`.
4. Update `Auction` model — fillable, `getEffectiveReturnPolicyAttribute()`.
5. Update `UpdateStorefrontRequest` validation.
6. Update `StorefrontController::update()` to persist fields.
7. Update `AuctionCrudController::publish()` to snapshot policy.
8. Update `AuctionController::show()` to pass policy to view.
9. Update `AdminDisputeController::show()` to display policy.
10. Write feature tests.

---

### F. Complexity & Priority
- **Complexity:** Low
- **Priority:** MVP

---

## Feature 80 — Seller Vacation Mode (Pause All Listings)

### A. Feature Overview
Allow sellers to activate "Vacation Mode" which pauses their active auction listings (extends all end times) or prevents new bids while they are unavailable. On deactivation, auctions resume normally.

**Business goal:** Retains sellers who might otherwise cancel listings; prevents negative buyer experiences from unresponsive sellers.

---

### B. Current State
- No vacation mode concept in the codebase.
- `Auction` has `end_time` and `status`; extending time is done manually by admin (`AuctionManagementController::extend()`).
- `BiddingStrategy::placeBid()` validates auction status and `end_time`.

---

### C. Required Changes

#### 1. Database

```php
// Migration: add_vacation_mode_to_users_table
Schema::table('users', function (Blueprint $table) {
    $table->boolean('vacation_mode')->default(false)->after('seller_rejected_reason');
    $table->timestamp('vacation_mode_started_at')->nullable()->after('vacation_mode');
    $table->timestamp('vacation_mode_ends_at')->nullable()->after('vacation_mode_started_at');
    // NULL = indefinite vacation
    $table->text('vacation_mode_message')->nullable()->after('vacation_mode_ends_at');
    $table->index('vacation_mode'); // for scheduler query
});

// Track paused state on auctions
Schema::table('auctions', function (Blueprint $table) {
    $table->boolean('paused_by_vacation')->default(false)->after('ending_soon_notified');
    $table->timestamp('paused_at')->nullable()->after('paused_by_vacation');
    $table->timestamp('original_end_time')->nullable()->after('paused_at');
    // Stores the original end_time before vacation extension
});
```

#### 2. Backend Logic

**`app/Services/VacationModeService.php`** — new service:

```php
namespace App\Services;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VacationModeService
{
    public function __construct(protected BiddingStrategy $biddingStrategy) {}

    /**
     * Activate vacation mode for a seller.
     * Options:
     *  - 'pause': extend all active auction end_times; no new bids accepted (via status check)
     *  - 'message_only': keep auctions running but show "seller is away" banner
     */
    public function activate(User $seller, ?Carbon $endsAt, string $message, string $mode = 'pause'): void
    {
        DB::transaction(function () use ($seller, $endsAt, $message, $mode) {
            $seller->update([
                'vacation_mode'            => true,
                'vacation_mode_started_at' => now(),
                'vacation_mode_ends_at'    => $endsAt,
                'vacation_mode_message'    => $message,
            ]);

            if ($mode === 'pause') {
                $this->pauseActiveAuctions($seller);
            }
        });

        Log::info('VacationModeService: activated', [
            'seller_id' => $seller->id,
            'ends_at'   => $endsAt?->toIso8601String(),
        ]);
    }

    /**
     * Deactivate vacation mode and restore auction end times.
     */
    public function deactivate(User $seller): void
    {
        DB::transaction(function () use ($seller) {
            $seller->update([
                'vacation_mode'            => false,
                'vacation_mode_started_at' => null,
                'vacation_mode_ends_at'    => null,
                'vacation_mode_message'    => null,
            ]);

            $this->resumePausedAuctions($seller);
        });

        Log::info('VacationModeService: deactivated', ['seller_id' => $seller->id]);
    }

    private function pauseActiveAuctions(User $seller): void
    {
        $active = Auction::where('user_id', $seller->id)
            ->where('status', Auction::STATUS_ACTIVE)
            ->where('paused_by_vacation', false)
            ->get();

        foreach ($active as $auction) {
            // Store original end time and extend far into the future
            $auction->update([
                'original_end_time'  => $auction->end_time,
                'paused_by_vacation' => true,
                'paused_at'          => now(),
                'end_time'           => now()->addYear(), // effectively paused
                'ending_soon_notified' => false, // reset so notification fires again on resume
            ]);
        }
    }

    private function resumePausedAuctions(User $seller): void
    {
        $paused = Auction::where('user_id', $seller->id)
            ->where('paused_by_vacation', true)
            ->get();

        foreach ($paused as $auction) {
            $vacationDuration = now()->diffInSeconds($auction->paused_at);
            $newEndTime = $auction->original_end_time->addSeconds($vacationDuration);

            $auction->update([
                'end_time'           => $newEndTime, // shift end time by duration paused
                'original_end_time'  => null,
                'paused_by_vacation' => false,
                'paused_at'          => null,
            ]);
        }
    }

    /**
     * Auto-deactivate vacation mode when vacation_mode_ends_at is reached.
     * Called by scheduler.
     */
    public function autoDeactivateExpired(): void
    {
        $sellers = User::where('vacation_mode', true)
            ->whereNotNull('vacation_mode_ends_at')
            ->where('vacation_mode_ends_at', '<=', now())
            ->get();

        foreach ($sellers as $seller) {
            $this->deactivate($seller);
        }
    }
}
```

**`app/Http/Controllers/Seller/VacationModeController.php`** — new controller:

```php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\VacationModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VacationModeController extends Controller
{
    public function __construct(protected VacationModeService $service) {}

    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ends_at' => ['nullable', 'date', 'after:now'],
            'message' => ['nullable', 'string', 'max:500'],
            'mode'    => ['nullable', Rule::in(['pause', 'message_only'])],
        ]);

        $this->service->activate(
            $request->user(),
            isset($validated['ends_at']) ? \Carbon\Carbon::parse($validated['ends_at']) : null,
            $validated['message'] ?? '',
            $validated['mode'] ?? 'pause',
        );

        return redirect()->route('seller.dashboard')
            ->with('status', 'Vacation mode activated. Your active auctions have been paused.');
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $this->service->deactivate($request->user());

        return redirect()->route('seller.dashboard')
            ->with('status', 'Vacation mode deactivated. Your auctions have been resumed.');
    }
}
```

**`routes/console.php`** — add scheduler job:

```php
// Auto-deactivate expired vacation modes
Schedule::call(fn () => app(VacationModeService::class)->autoDeactivateExpired())
    ->everyFiveMinutes()
    ->name('auto-deactivate-vacation-mode')
    ->withoutOverlapping();
```

**`app/Services/Bidding/BidValidator.php`** — add vacation mode check:

```php
protected function ensureAuctionActive(Auction $auction): void
{
    if ($auction->status !== Auction::STATUS_ACTIVE) {
        throw BidValidationException::auctionNotActive();
    }
    // Block bidding on paused auctions
    if ($auction->paused_by_vacation) {
        throw new BidValidationException(
            'This auction is temporarily paused while the seller is on vacation.',
            'auction_paused', [], 422
        );
    }
}
```

#### 3. API / Routes

```php
// Inside seller middleware group
Route::post('/vacation-mode/activate',   [VacationModeController::class, 'activate'])->name('seller.vacation.activate');
Route::post('/vacation-mode/deactivate', [VacationModeController::class, 'deactivate'])->name('seller.vacation.deactivate');
```

#### 4. Frontend (contract)
- Seller dashboard: "Vacation Mode" toggle card showing current status, optional return date, and message.
- Auction detail page: Banner "This seller is on vacation until [date]. [message]" when `seller.vacation_mode === true`.
- Seller auction list: Paused auctions shown with "Paused (Vacation)" badge.
- Bid button: Disabled with tooltip "Bidding paused — seller on vacation" when `paused_by_vacation === true`.

---

### D. Dependencies & Risks
- **Anti-snipe extension interactions:** If an auction was in its snipe window when vacation mode activated, resuming it may re-enter the snipe window with the new end time. This is acceptable behavior.
- **Redis price keys:** Paused auctions still have Redis keys. The keys do not need to be cleared — they are valid when the auction resumes (price hasn't changed while paused).
- **Ending soon notifications:** Reset `ending_soon_notified = false` when pausing so the notification fires again after resume with the new end time.
- **Max auction duration:** If vacation lasts 2 years and the original end was tomorrow, the end_time shift could create oddly long auctions. Consider capping vacation mode at 90 days and refusing to activate for auctions ending within 24 hours (require manual cancel instead).

---

### E. Implementation Steps
1. Migration: add `vacation_mode*` columns to `users`.
2. Migration: add `paused_by_vacation`, `paused_at`, `original_end_time` to `auctions`.
3. Update `User` model — fillable, casts.
4. Update `Auction` model — fillable, casts.
5. Create `VacationModeService` with `activate()`, `deactivate()`, `autoDeactivateExpired()`.
6. Update `BidValidator::ensureAuctionActive()` to check `paused_by_vacation`.
7. Create `VacationModeController`.
8. Register routes.
9. Add auto-deactivate scheduler entry.
10. Add `BidValidationException` case for `'auction_paused'` error code.
11. Write feature tests covering: activate pauses all auctions, resume shifts end times correctly, bidding blocked during vacation, auto-deactivate on expiry.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Growth

---

## Shared Components Across Seller Tool Features

| Component | Used By Features |
|-----------|-----------------|
| `WalletService::withdraw()` | Listing Fee (67), Tax refunds |
| `InvoiceService` / DomPDF | Tax Documents (73) |
| `AuditLog::record()` | All write operations |
| `BidValidator` | Vacation Mode (80) |
| `UpdateStorefrontRequest` | Return Policy (74) |
| `AuctionCrudController::publish()` | Listing Fee (67), Policy snapshot (74) |

## Quick Wins
- **Feature 74** (Return Policy): 2 migrations + model changes + form fields — completable in 1 day.
- **Feature 22** (Preview) from general-auction.md: 0 migrations — completable in half a day.

## Architectural Improvements
1. **`SellerSettingsService`**: Extract all seller profile/settings persistence into a dedicated service, rather than ad-hoc `$user->update([...])` calls scattered across controllers. Covers: bio, slug, avatar, return policy, vacation mode, notification preferences.
2. **Seller dashboard widgets**: Introduce a `DashboardWidget` contract so new features (vacation status, tax reminders, listing fee balance) can register themselves as dashboard widgets without modifying `SellerDashboardController`.