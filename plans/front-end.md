# Auction Platform — Frontend Implementation Plan

> Stack baseline inferred from existing code: Laravel 12 Blade + Alpine.js + Tailwind CSS + Vite. Real-time via Reverb/Echo. FilePond for uploads. Tom Select for multi-selects. Chart.js for charts. Existing design tokens in `resources/css/tokens.css`.

---

## 1. Frontend Architecture Summary

### Framework & Rendering Strategy

| Concern | Decision | Rationale |
|---|---|---|
| Rendering | **Laravel Blade + Alpine.js (existing)** | SSR for SEO-critical pages; Alpine for reactive islands |
| Real-time | **Laravel Echo + Reverb WebSockets** | Already wired in `bid-events.js`; BidEventBus abstraction exists |
| Form handling | **Standard `<form>` + Fetch API for async** | Predictable; aligns with existing pattern |
| State mgmt | **Alpine.js local state + Blade server state** | No global store needed; `x-data` per component |
| File uploads | **FilePond** (already installed) | Used for auction images; extend to certs, avatars |
| Charts | **Chart.js** (already installed) | Used in analytics and seller dashboard |
| i18n | **Laravel localization** + `SetUserLocale` middleware | Locale-aware Blade rendering |
| Testing | **Pest + Laravel Dusk (E2E)** | Pest already in use; add Dusk for browser tests |

### Design System Foundation

The existing `tokens.css` defines the design system. All new components must consume these tokens exclusively — no hardcoded hex values.

```css
/* Existing tokens to use */
--color-primary, --color-danger, --color-success, --color-warning
--shadow-sm, --shadow-md, --shadow-lg
--radius-sm through --radius-full
--space-1 through --space-16
```

**New tokens to add** for auction-specific UI:

```css
:root {
  --color-bin-badge: #f59e0b;        /* Buy It Now gold */
  --color-lot-badge: #7c3aed;        /* Lot auction purple */
  --color-vacation: #6b7280;         /* Vacation mode gray */
  --color-cert-verified: #16a34a;    /* Auth cert green */
  --color-preview-banner: #f59e0b;   /* Preview mode amber */
}
```

### Component Architecture Principles

1. **Blade components** for all reusable UI: `<x-auction.card>`, `<x-ui.badge>` already exist — extend this pattern.
2. **Alpine components** (via `x-data="functionName()"`) for interactive islands: bid panel, comparison bar, support chat.
3. **Server-driven state**: Blade passes initial data; Alpine owns ephemeral UI state only.
4. **Optimistic updates** for watch/follow toggles; **pessimistic** for bids and purchases.
5. **Skeleton loaders** (`<x-skeleton.auction-card>` already exists) for all async-loaded sections.

---

## 2. Feature Plans by Section

---

### Section 1: General & Auction Features

---

#### Feature 7 — Buy It Now (Instant Purchase)

**A. Feature Overview**

A "Buy It Now" button appears on eligible auctions. Clicking it purchases the auction immediately at the fixed BIN price, bypassing bidding. The button vanishes when bids exceed 75% of the BIN price (real-time).

**Primary personas**: Buyer (price-sensitive), Seller (set-and-forget pricing).

**B. Frontend Surface Area**

- **Auction card** (`resources/views/components/auction/card.blade.php`): BIN price badge, overlaid on price section.
- **Auction detail page** (`auctions/show.blade.php`): "Buy It Now for $X" button in the bidding panel, below the bid form.
- **Seller create/edit form**: BIN price field + enable toggle in the Pricing section.
- **Confirmation modal**: Full-screen overlay before purchase commits.
- **Success redirect**: Invoice page post-purchase.

**Mobile**: BIN button full-width, stacked below bid form. Badge on card visible at all breakpoints.

**C. UX Flow**

1. User sees auction with BIN badge and `isBuyItNowAvailable === true`.
2. Clicks "Buy It Now for $249.99".
3. Confirmation modal appears: "You are about to purchase this item for $249.99. This action is final and will close the auction immediately." — two CTAs: **Confirm Purchase** / **Cancel**.
4. On confirm → `POST /auctions/{id}/buy-it-now` with optimistic loading state on button.
5. **Success**: Redirect to `/user/invoices/{id}` with `?source=bin` toast banner.
6. **Failure (BIN expired)**: Toast error "Buy It Now is no longer available. This auction had a bid placed." — BIN button disappears, bid form remains active.
7. **Failure (insufficient funds)**: Toast error with link to wallet top-up.
8. **Loading state**: Button shows spinner, disabled, text "Processing…".
9. **Empty**: If `isBuyItNowAvailable === false`, the entire BIN section is hidden with no placeholder.

**Real-time**: Subscribe to `auctions.{id}` channel. On `bid.placed` event, re-check `isBuyItNowAvailable`. If expired, fade-remove BIN button via Alpine transition.

```javascript
// In auction show page Alpine component
window.addEventListener('bid:placed', (e) => {
  if (e.detail.auctionId === this.auctionId && !e.detail.binAvailable) {
    this.binAvailable = false;
  }
});
```

**D. API Integration**

| Method | Endpoint | Notes |
|---|---|---|
| `POST` | `/auctions/{id}/buy-it-now` | Pessimistic — no optimistic update; wait for server |
| `GET` | `/auctions/{id}/live-state` | Returns `buy_it_now_available`, `buy_it_now_price` |

Response shape on success:
```json
{ "success": true, "invoice_id": 42, "message": "Purchase successful!" }
```

Response shape on failure:
```json
{ "success": false, "message": "Buy It Now is no longer available." }
```

**Caching**: No caching — BIN availability is time-sensitive. Poll via `liveState()` every 4s (already implemented for prices).

**E. Frontend Architecture**

New Blade component: `resources/views/components/auction/bin-button.blade.php`

```blade
@props(['auction', 'binPrice', 'available' => true])
<div x-data="binPurchase({{ $auction->id }}, {{ $binPrice }})"
     x-show="available"
     x-transition>
  <div class="mt-4 rounded-xl border-2 border-amber-400 bg-amber-50 p-4">
    <p class="text-xs text-amber-700 font-semibold uppercase tracking-wide mb-2">Buy It Now</p>
    <button @click="confirm()" :disabled="loading"
            class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 px-4 rounded-lg">
      <span x-show="!loading">Buy for {{ format_price($binPrice) }}</span>
      <span x-show="loading">Processing…</span>
    </button>
  </div>
  <!-- Confirmation modal -->
  <div x-show="showModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    ...
  </div>
</div>
```

New Alpine function: `resources/js/bin-purchase.js`

State: `{ available, loading, showModal, binPrice, auctionId }`

**F. Design System / Reusability**

- `<x-auction.bin-button>` — used on `auctions/show.blade.php`
- BIN price badge on `<x-auction.card>` — new `<x-ui.badge color="amber">BIN: $X</x-ui.badge>`
- Confirmation modal pattern → reuse `<x-modal>` component (already exists)

**G. Dependencies & Risks**

- **Race condition UX**: Two users click BIN simultaneously. Frontend: show "processing" on first click. If server 422s, display error. UX is safe.
- **Real-time BIN expiry**: If Echo socket disconnects, the BIN button may persist stale. Mitigation: 4s polling via `liveState()` already in place acts as fallback.
- **Currency display**: BIN price is shown in display currency via `format_price()`. Submission is always in USD.

**H. Implementation Steps**

- [ ] Add `buy_it_now_price`, `buy_it_now_enabled` fields to seller create/edit pricing section
- [ ] Create `<x-auction.bin-button>` Blade component
- [ ] Create `binPurchase()` Alpine function in `resources/js/bin-purchase.js`
- [ ] Add BIN badge to `<x-auction.card>` (conditional on `$auction->isBuyItNowAvailable()`)
- [ ] Wire confirmation modal using existing `<x-modal>` component
- [ ] Handle `bid.placed` WebSocket event to hide BIN button reactively
- [ ] Add redirect logic to invoice page on success
- [ ] Unit test: `binPurchase()` state transitions
- [ ] E2E: buyer clicks BIN → confirmation → purchase success

**I. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

**J. Testing Strategy**

- **Unit**: Alpine `binPurchase()` state machine (idle → confirming → loading → success/error)
- **Component**: `<x-auction.bin-button>` renders conditionally based on `available` prop
- **Integration**: `POST /auctions/{id}/buy-it-now` returns correct redirect
- **E2E**: Full BIN purchase flow; concurrent BIN attempt shows error gracefully

---

#### Feature 8 — Reserve Price Reveal Toggle

**A. Feature Overview**

Seller chooses whether the reserve price amount is displayed publicly. Buyers always see the "Reserve Met / Not Met" indicator; the dollar amount is optional.

**B. Frontend Surface Area**

- **Auction detail** — reserve section in specs panel: shows `$X.XX` or "Not disclosed".
- **Auction card** — reserve badge (`Reserve Met` / `Reserve Not Met`) — unchanged.
- **Seller create/edit** — checkbox: "Show reserve price to bidders".
- **Admin auction detail** — always shows reserve amount regardless of toggle.

**C. UX Flow**

- If `reserve_price !== null` → "Reserve: $X.XX"
- If `reserve_price === null` (hidden) → "Reserve: Not disclosed"
- `reserve_met` badge always shows regardless of visibility setting

**D. API Integration**

`AuctionResource` returns `reserve_price: null` when hidden. Frontend reads this — no additional endpoint needed.

**E. Frontend Architecture**

In `auctions/show.blade.php`, in the specs grid:

```blade
@if($auction->hasReserve())
  <div class="flex justify-between py-2 border-b border-gray-100">
    <span class="text-gray-500">Reserve Price</span>
    <span class="font-medium text-gray-900">
      {{ $auction->reserve_price !== null ? format_price((float)$auction->reserve_price) : 'Not disclosed' }}
    </span>
  </div>
@endif
```

Seller form addition in `seller/auctions/create.blade.php` and `edit.blade.php`:

```blade
<label class="flex items-center gap-2 mt-2">
  <input type="checkbox" name="reserve_price_visible" value="1"
         @checked(old('reserve_price_visible', $auction->reserve_price_visible ?? false))
         class="rounded border-gray-300 text-indigo-600">
  <span class="text-sm text-gray-700">Show reserve price to bidders</span>
</label>
```

**F. Complexity & Priority**

- **Complexity**: Low
- **Priority**: MVP

---

#### Feature 9 — Auction Re-listing (Clone & Re-post)

**A. Feature Overview**

"Re-list" button on completed/cancelled auction detail and index page. Clones the auction as a draft, redirects seller to edit form.

**B. Frontend Surface Area**

- **Seller auction index** (`seller/auctions/index.blade.php`): "Re-list" link in action column for `completed`/`cancelled` rows.
- **Seller auction edit** — yellow banner: "No end date set — configure schedule before publishing."
- **Seller auction show** (if exposed) — "Re-list" button in actions area.

**C. UX Flow**

1. Seller clicks "Re-list" → confirmation: "This will create a new draft based on this auction. Continue?"
2. `POST /seller/auctions/{id}/clone`
3. **Success**: Redirect to `seller/auctions/{new_id}/edit` with flash: "Auction cloned. Set new dates before publishing."
4. **Failure**: Toast error (edge case: permission denied).
5. Warning banner in edit form if `end_time === null`.

**D. API Integration**

| Method | Endpoint | Notes |
|---|---|---|
| `POST` | `/seller/auctions/{id}/clone` | Redirects on success |

**E. Frontend Architecture**

In `seller/auctions/index.blade.php`, add to actions:

```blade
@if(in_array($auction->status, ['completed', 'cancelled']))
  <form method="POST" action="{{ route('seller.auctions.clone', $auction) }}"
        onsubmit="return confirm('Clone this auction as a new draft?')">
    @csrf
    <button type="submit" class="text-violet-600 hover:text-violet-800">Re-list</button>
  </form>
@endif
```

Warning banner in `seller/auctions/edit.blade.php`:

```blade
@if($auction->isDraft() && !$auction->end_time && $auction->cloned_from_auction_id)
  <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900 mb-6">
    ⚠ No end date set — this auction was cloned. Configure schedule before publishing.
  </div>
@endif
```

**F. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 14 — Group / Lot Auctions

**A. Feature Overview**

Sellers create "Lot" auctions bundling multiple items. Public view shows a collapsible item list. Auction cards show "Lot of N items" badge.

**B. Frontend Surface Area**

- **Seller create/edit**: Toggle "This is a Lot Auction" → reveals dynamic lot item manager.
- **Auction card**: "Lot of N items" badge below title.
- **Auction detail**: Collapsible "What's Included" section with lot items list + per-item images.
- **Admin auction detail**: Lot items shown in specs section.

**C. UX Flow — Lot Item Manager (Seller)**

1. Toggle "This is a Lot Auction" checkbox → lot items section slides down.
2. Initial empty state: "No items added yet. Add at least one item."
3. "+ Add Item" button → inline form: name, qty, condition, description, image upload (FilePond single file).
4. Items shown as cards with drag-to-reorder (Sortable.js or CSS drag handles).
5. "✕" to remove item. Edit icon to expand inline edit.
6. Max 50 items — counter badge: "3 / 50 items".
7. On publish validation: at least 1 lot item required if `is_lot = true`.

**D. API Integration**

| Method | Endpoint | Notes |
|---|---|---|
| `POST` | `/seller/auctions/{id}/lot-items` | Create lot item |
| `PATCH` | `/seller/auctions/{id}/lot-items/{item}` | Update |
| `DELETE` | `/seller/auctions/{id}/lot-items/{item}` | Remove |
| `POST` | `/seller/auctions/{id}/lot-items/reorder` | `{ order: [1,3,2] }` |
| `POST` | `/seller/auctions/{id}/lot-items/{item}/image` | FilePond upload |

All lot item operations are optimistic — update local state immediately, rollback on error.

**E. Frontend Architecture**

New Alpine component: `lotItemManager(auctionId)`

```javascript
{
  items: [],      // fetched on init
  draft: null,    // item being edited
  saving: false,
  addItem() { this.draft = { name:'', qty:1, condition:'', description:'' }; },
  async saveItem() { ... POST or PATCH ... },
  async removeItem(id) { ... DELETE ... },
  async reorder(newOrder) { ... POST reorder ... }
}
```

New Blade partial: `seller/auctions/partials/lot-item-manager.blade.php`
New Blade component for public view: `<x-auction.lot-items :items="$auction->lotItems">`

Public detail collapsible:

```blade
<details class="mt-6 border rounded-xl">
  <summary class="px-4 py-3 font-semibold cursor-pointer flex items-center justify-between">
    What's Included ({{ $auction->lot_item_count }} items)
    <svg ...chevron... />
  </summary>
  <div class="px-4 pb-4 space-y-3">
    @foreach($auction->lotItems as $item)
      <div class="flex gap-4 p-3 bg-gray-50 rounded-lg">
        @if($item->getFirstMedia('item_images'))
          <img src="{{ $item->getFirstMedia('item_images')->getUrl('thumbnail') }}" class="w-16 h-16 rounded object-cover">
        @endif
        <div>
          <p class="font-medium">{{ $item->name }} <span class="text-gray-500">×{{ $item->quantity }}</span></p>
          @if($item->condition) <x-ui.badge color="blue" size="xs">{{ $item->condition }}</x-ui.badge> @endif
          @if($item->description) <p class="text-sm text-gray-600 mt-1">{{ $item->description }}</p> @endif
        </div>
      </div>
    @endforeach
  </div>
</details>
```

**F. Complexity & Priority**

- **Complexity**: High
- **Priority**: Growth

---

#### Feature 21 — Auction Drafts Auto-Save

**A. Feature Overview**

Seller's draft auction form auto-saves 2s after the user stops typing. Visual indicator: "Saving…" → "Saved at HH:MM".

**B. Frontend Surface Area**

- **Seller create/edit form toolbar** — "auto-save indicator" slot next to publish button.
- No new pages.

**C. UX Flow**

1. User types in title, description, or any whitelisted field.
2. After 2 seconds of inactivity → debounced `PATCH /seller/auctions/{id}/auto-save`.
3. Indicator transitions: `idle` → `saving` → `saved at HH:MM` → (fade back to idle after 5s).
4. On `beforeunload` with dirty state → synchronous attempt (best-effort).
5. If response 422 (not a draft): indicator shows "Auto-save unavailable" and suppresses future calls.
6. Throttled by server (30/min) → 429 silently suppressed on frontend.

**D. API Integration**

| Method | Endpoint | Notes |
|---|---|---|
| `PATCH` | `/seller/auctions/{id}/auto-save` | Partial data, whitelisted fields only |

Request: `{ title, description, video_url, condition, ... }`
Response: `{ saved: true, auto_saved_at: "2026-04-22T12:34:56Z" }`

No caching. Fire-and-forget pattern with error suppression.

**E. Frontend Architecture**

New Alpine component: `autoSave(auctionId, url)`

```javascript
{
  status: 'idle', // 'idle' | 'saving' | 'saved' | 'error'
  savedAt: null,
  dirty: false,
  timer: null,
  init() {
    document.querySelectorAll('[data-autosave]').forEach(el => {
      el.addEventListener('input', () => this.schedule());
    });
    window.addEventListener('beforeunload', () => {
      if (this.dirty) this.flush();
    });
  },
  schedule() {
    this.dirty = true;
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.flush(), 2000);
  },
  async flush() { ... PATCH ... }
}
```

Mark form fields with `data-autosave` attribute. Add indicator div to the form toolbar.

Add `data-autosave` to: `#title`, `#description`, `#video_url`, `#condition`, `#sku`, `#serial_number`, `[name=reserve_price_visible]`.

**F. Complexity & Priority**

- **Complexity**: Low
- **Priority**: MVP

---

#### Feature 22 — Auction Preview Mode Before Publishing

**A. Feature Overview**

Seller can view their draft auction exactly as buyers would see it, with a prominent "Preview Mode" banner and no bidding controls.

**B. Frontend Surface Area**

- **Seller auction edit** — "Preview" button in action row.
- **`auctions/show.blade.php`** — conditional preview banner + hidden bid form/BIN/watch when `$isPreview === true`.

**C. UX Flow**

1. Seller clicks "Preview" button → navigates to `/seller/auctions/{id}/preview`.
2. Page renders `auctions/show.blade.php` with `$isPreview = true`.
3. Amber banner at top: "📋 Preview Mode — this auction is not yet published."
4. Bid form, BIN button, watch button, report button — all hidden.
5. Two action buttons in banner: "← Back to Edit" and "Publish Now" (form POST).
6. Breadcrumb: Dashboard → My Auctions → Edit → Preview.

**D. Frontend Architecture**

In `auctions/show.blade.php`, already has `@if($isPreview ?? false)` block. Extend it:

```blade
@if($isPreview ?? false)
  <div class="rounded-2xl border-2 border-amber-400 bg-amber-50 px-6 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
      <p class="font-semibold text-amber-900">📋 Preview Mode — this auction is not yet published.</p>
      <p class="text-sm text-amber-700 mt-1">This is how buyers will see your listing.</p>
    </div>
    <div class="flex gap-3">
      <a href="{{ route('seller.auctions.edit', $auction) }}" class="...">← Back to Edit</a>
      <form method="POST" action="{{ route('seller.auctions.publish', $auction) }}">
        @csrf
        <button type="submit" class="...bg-amber-600...">Publish Now</button>
      </form>
    </div>
  </div>
@endif
```

Also conditionally hide: `#bid-form`, `#watch-btn`, `.bin-button`, `.report-button` when `$isPreview`.

**E. Complexity & Priority**

- **Complexity**: Low
- **Priority**: MVP

---

### Section 2: Seller Tools & Fees

---

#### Feature 67 — Seller Listing Fee

**A. Feature Overview**

When a seller publishes an auction, a listing fee is deducted from their wallet. The fee is previewed before publishing.

**B. Frontend Surface Area**

- **Publish confirmation dialog** — "Listing fee: $X.XX will be deducted from your wallet."
- **Seller wallet page** — listing fee transactions tagged `Listing fee for auction: {title}`.
- **Seller dashboard** — listing fee balance card (optional).
- **Admin panel** — Listing Fee Tier CRUD table under Settings.

**C. UX Flow**

1. Seller clicks "Publish Auction".
2. Before redirect, fetch `GET /seller/auctions/{id}/listing-fee-preview` → `{ listing_fee: 2.50 }`.
3. If `listing_fee > 0`: show confirmation: "Publishing this auction will deduct **$2.50** from your wallet (balance: $X.XX). Proceed?"
4. If wallet balance < fee: "Insufficient wallet balance. [Top up wallet →]" — publish button disabled.
5. If `listing_fee === 0`: publish without confirmation.
6. On publish POST → redirect or error.

**D. API Integration**

| Method | Endpoint | Notes |
|---|---|---|
| `GET` | `/auctions/{id}/listing-fee-preview` | Pre-publish fee check |
| `POST` | `/seller/auctions/{id}/publish` | Deducts fee server-side |

**E. Frontend Architecture**

Modify publish button in `seller/auctions/edit.blade.php` to use Alpine:

```javascript
x-data="listingFeePublish({
  previewUrl: '{{ route('seller.auctions.listing-fee-preview', $auction) }}',
  publishUrl: '{{ route('seller.auctions.publish', $auction) }}',
  walletBalance: {{ auth()->user()->availableBalance() }}
})"
```

**F. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 73 — Seller Tax Document Generation

**A. Feature Overview**

Sellers generate downloadable tax summary PDFs for a selected period (annual/quarterly/monthly).

**B. Frontend Surface Area**

- **Seller sidebar nav** — new "Tax Documents" link.
- **`seller/tax-documents/index.blade.php`** (already exists) — period selector form + documents table.
- **Download link** — streams PDF.

**C. UX Flow**

1. Seller selects Period Type, Year, optionally Quarter/Month.
2. Clicks "Generate Report" → POST → redirect to download or back with flash.
3. Previously generated documents listed in table with download links.
4. Quarter/Month fields show/hide based on Period Type selection (Alpine `x-show`).

The view already exists. Key addition: Alpine-driven period selector toggle (already in `seller/tax-documents/index.blade.php` via `@push('scripts')`).

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 74 — Seller Return / Refund Policy Setting

**A. Feature Overview**

Sellers define their return policy. Displayed on auction detail pages in a "Seller Policy" collapsible section.

**B. Frontend Surface Area**

- **Seller storefront edit** — Return Policy section with radio buttons.
- **Auction detail** — `<details>` collapsible "Seller Policy" section.
- **Dispute creation** — pre-filled note showing the effective policy.

**C. Frontend Architecture**

In `seller/storefront/edit.blade.php`, add after bio section:

```blade
<div x-data="{ policyType: '{{ old('return_policy_type', $user->return_policy_type) }}' }">
  <label class="block text-sm font-medium text-gray-700 mb-2">Return Policy</label>
  <div class="space-y-2">
    @foreach(['no_returns' => 'No returns accepted', 'returns_accepted' => 'Returns accepted', 'custom' => 'Custom policy'] as $val => $label)
      <label class="flex items-center gap-2">
        <input type="radio" name="return_policy_type" value="{{ $val }}"
               x-model="policyType" class="text-indigo-600">
        <span class="text-sm">{{ $label }}</span>
      </label>
    @endforeach
  </div>
  <div x-show="policyType === 'returns_accepted'" class="mt-3">
    <input type="number" name="return_window_days" min="1" max="90" ...>
  </div>
  <div x-show="policyType === 'custom'" class="mt-3">
    <textarea name="return_policy_custom" ...></textarea>
  </div>
</div>
```

On auction detail, add collapsible policy section:

```blade
<details class="mt-4 border rounded-xl">
  <summary class="px-4 py-3 font-medium cursor-pointer">Seller Policy</summary>
  <div class="px-4 pb-4 text-sm text-gray-700">
    {{ $auction->effective_return_policy }}
  </div>
</details>
```

**D. Complexity & Priority**

- **Complexity**: Low
- **Priority**: MVP

---

#### Feature 80 — Seller Vacation Mode

**A. Feature Overview**

Sellers activate Vacation Mode to pause all active listings. A banner appears on their auction detail pages. Bid button is disabled on paused auctions.

**B. Frontend Surface Area**

- **Seller dashboard** — Vacation Mode card with activate/deactivate toggle + return date picker + message textarea.
- **Auction detail** (buyer view) — amber banner "Seller is on vacation until [date]."
- **Bid form** — disabled with tooltip "Bidding paused — seller on vacation."
- **Seller auction index** — "Paused (Vacation)" badge in status column.

**C. UX Flow**

**Activate:**
1. Seller toggles "Vacation Mode" on dashboard.
2. Optional: select return date (date picker) + custom message.
3. Confirm → `POST /seller/vacation-mode/activate` → flash success + all active listings show "Paused" badge.

**Deactivate:**
1. Click "Return from Vacation" → `POST /seller/vacation-mode/deactivate` → auctions resume.

**Buyer view:**
- If `$auction->paused_by_vacation === true` → amber banner + disabled bid form.
- Bid form input disabled + tooltip on hover.

**D. Frontend Architecture**

Vacation Mode card in `seller/dashboard.blade.php`:

```blade
<div x-data="vacationMode({
  active: {{ auth()->user()->vacation_mode ? 'true' : 'false' }},
  activateUrl: '{{ route('seller.vacation.activate') }}',
  deactivateUrl: '{{ route('seller.vacation.deactivate') }}'
})" class="rounded-xl border p-6">
  ...toggle, date picker, message textarea...
</div>
```

Auction detail modification — add above bid form:

```blade
@if($auction->paused_by_vacation)
  <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 mb-4">
    <p class="text-sm font-medium text-amber-900">🌴 This seller is on vacation.</p>
    @if($auction->seller->vacation_mode_message)
      <p class="text-sm text-amber-800 mt-1">{{ $auction->seller->vacation_mode_message }}</p>
    @endif
    @if($auction->seller->vacation_mode_ends_at)
      <p class="text-xs text-amber-700 mt-1">Expected return: {{ $auction->seller->vacation_mode_ends_at->format('M d, Y') }}</p>
    @endif
  </div>
  <div class="opacity-50 pointer-events-none">
    @include('auctions.partials.bid-form')
  </div>
@else
  @include('auctions.partials.bid-form')
@endif
```

**E. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

### Section 3: User Accounts, Notifications & Interaction

---

#### Feature 43 — User Referral Program

**A. Feature Overview**

Users share a referral link (`?ref=CODE`). Both referrer and referee earn wallet credits.

**B. Frontend Surface Area**

- **User dashboard** — new "Referrals" tab or card with link display + copy button.
- **`user/referrals/index.blade.php`** (already exists) — referral link, stats, referral table.
- **Registration form** — hidden `referral_code` field pre-filled from URL/session.

**C. UX Flow**

- Copy-to-clipboard button: "Copy Link" → copies `https://…?ref=ABCD1234` → button changes to "Copied ✓" for 2s.
- Referral table shows: name, join date, status (Pending/Credited), reward amount.
- Stats: Total Earned (green) + Pending (amber).

**D. Frontend Architecture**

Copy button Alpine pattern:

```blade
<div x-data="{ copied: false }">
  <input type="text" readonly value="{{ $referralLink }}" class="...">
  <button @click="navigator.clipboard.writeText('{{ $referralLink }}').then(() => { copied = true; setTimeout(() => copied = false, 2000); })">
    <span x-show="!copied">Copy Link</span>
    <span x-show="copied" class="text-green-600">Copied ✓</span>
  </button>
</div>
```

In `auth/register.blade.php`, add:
```blade
<input type="hidden" name="referral_code" value="{{ session('referral_code', request('ref', '')) }}">
```

**E. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 45 — User Follow Seller

**A. Feature Overview**

Users follow verified sellers. Follower count shown on storefronts. Followers receive new listing notifications.

**B. Frontend Surface Area**

- **Seller storefront** — "Follow / Unfollow" toggle button + follower count.
- **User dashboard** — "Following" section listing followed sellers with latest auctions.

**C. UX Flow**

- Toggle button: optimistic update (state flips immediately, API call in background).
- If follow fails → revert + toast error.
- Follower count increments/decrements optimistically.

**D. Frontend Architecture**

In `seller/storefront/show.blade.php`:

```blade
@auth
<div x-data="followSeller({
  sellerId: {{ $seller->id }},
  following: {{ $isFollowing ? 'true' : 'false' }},
  count: {{ $followerCount }},
  url: '{{ route('sellers.follow', $seller) }}'
})">
  <button @click="toggle()" class="...">
    <span x-text="following ? 'Following ✓' : 'Follow'"></span>
  </button>
  <span class="text-sm text-gray-500" x-text="count + ' followers'"></span>
</div>
@endauth
```

**E. Complexity & Priority**

- **Complexity**: Low
- **Priority**: Growth

---

#### Feature 46 — User Block User

**A. Feature Overview**

Users block others, preventing messages and bid history visibility.

**B. Frontend Surface Area**

- **User profile page** — three-dot menu with "Block User" / "Unblock User".
- **User settings** — "Blocked Users" list with unblock action.

**C. UX Flow**

Three-dot menu Alpine dropdown: on "Block User" → confirmation dialog → `POST /users/{id}/block` → toast "User blocked."

**D. Complexity & Priority**

- **Complexity**: Low
- **Priority**: Growth

---

#### Feature 48 — Bid Retraction Request

**A. Feature Overview**

Bidders request retraction of their current highest bid. Admin approves/declines.

**B. Frontend Surface Area**

- **User bid history** — "Request Retraction" link on eligible bids.
- **Retraction form** — reason textarea.
- **Admin panel** — new "Bid Retractions" queue page.
- **Email notification** on approval/decline.

**C. UX Flow**

1. User sees "Request Retraction" on their active winning bid.
2. Clicks → modal: reason textarea + "Submit Request" button.
3. `POST /bids/{id}/retract` → toast "Retraction request submitted."
4. Admin sees bid retraction queue at `/admin/bid-retractions` with approve/decline actions.
5. On approve: escrow released, auction price reverts, user notified.

**D. Complexity & Priority**

- **Complexity**: High
- **Priority**: Growth

---

#### Feature 50 — Bidding Power-Ups / Credits

**A. Feature Overview**

Virtual bid credits system. Users earn/spend credits for power-ups (extra auto-bid slots, listing fee waivers).

**B. Frontend Surface Area**

- **User dashboard header** — credit balance badge.
- **Power-ups page** (`/dashboard/credits`) — catalog of power-ups with credit costs.
- **Credit transaction history**.

**C. UX Flow**

- Power-up card: name, description, cost badge. "Activate" button → confirmation → spend credits.
- If insufficient credits: button disabled + tooltip "Not enough credits. Earn more by…"
- Credit balance updates optimistically.

**D. Complexity & Priority**

- **Complexity**: High
- **Priority**: Scaling

---

#### Feature 55 — Outbid Threshold Alerts

**A. Feature Overview**

Users set custom outbid thresholds on watched auctions. Reduces notification noise.

**B. Frontend Surface Area**

- **Watch dialog** (when clicking Watch button) — optional fields: "Only notify if outbid by more than $__" + "Alert me when price reaches $__".
- **Notification preferences page** — default outbid threshold field.

**C. Frontend Architecture**

Extend `toggleWatch()` in `auctions/show.blade.php` to open a settings modal before creating the watcher:

```blade
<div x-data="watchSettings({
  auctionId: {{ $auction->id }},
  watching: {{ $isWatching ? 'true' : 'false' }},
  watchUrl: '{{ route('auctions.watch', $auction) }}'
})">
  <button @click="open = true" id="watch-btn">Watch</button>
  <div x-show="open" class="...modal...">
    <input type="number" x-model="outbidThreshold" placeholder="Min outbid amount ($)">
    <input type="number" x-model="priceAlertAt" placeholder="Alert when price reaches ($)">
    <button @click="save()">Save Preferences</button>
  </div>
</div>
```

**D. Complexity & Priority**

- **Complexity**: Low–Medium
- **Priority**: Growth

---

#### Feature 56 — User Language / Locale Preference

**A. Feature Overview**

Users select UI language. Applied via `SetUserLocale` middleware on next request.

**B. Frontend Surface Area**

- **Notification preferences page** — language selector (already has `locale` input field).
- **Currency selector in navbar** — already implemented; locale follows same save pattern.

**C. UX Flow**

Dropdown in notification preferences: save triggers full form POST → page reloads in new locale.

**D. Complexity & Priority**

- **Complexity**: Medium (mostly translation file work)
- **Priority**: Scaling

---

#### Feature 58 — User Account Deactivation

**A. Feature Overview**

Users deactivate their account (soft delete with 30-day reactivation window).

**B. Frontend Surface Area**

- **Profile edit page** — replace "Delete Account" modal with two options: "Deactivate" (soft) and "Permanently Delete" (hard).
- **Reactivation page** (`/account/reactivate`) — simple form with "Reactivate My Account" button.

**C. UX Flow**

In `profile/partials/delete-user-form.blade.php`, add deactivate option alongside delete:

```blade
<div class="space-y-4">
  <!-- Deactivate (soft) -->
  <div class="border rounded-xl p-4">
    <h4 class="font-medium">Deactivate Account</h4>
    <p class="text-sm text-gray-600">Temporarily disable your account. Reactivate within 30 days.</p>
    <form method="POST" action="{{ route('profile.deactivate') }}" class="mt-3">
      @csrf
      <input type="password" name="password" placeholder="Confirm password" required>
      <button type="submit" class="...">Deactivate</button>
    </form>
  </div>
  <!-- Delete (hard) - existing modal -->
</div>
```

Reactivation page: minimal centered layout with "Welcome back" heading and "Reactivate" CTA.

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: MVP

---

#### Feature 59 — User Export Data (GDPR)

**A. Feature Overview**

Users request a ZIP archive of all their data. Async generation; download link sent via notification.

**B. Frontend Surface Area**

- **User settings / profile edit** — "Export My Data" section with "Request Export" button.
- **Dashboard notification** — "Your data export is ready. [Download]"

**C. UX Flow**

1. User clicks "Request Export" → `POST /dashboard/data-export`.
2. If no pending export: flash "Export requested. You'll be notified when ready."
3. If export already ready: redirect to download link.
4. If processing: flash "Export in progress. Check back soon."
5. Download link expires in 7 days — banner shows expiry date.

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: MVP (GDPR)

---

### Section 4: Product & Category Enhancements

---

#### Feature 90 — Product Authenticity Certificate Upload

**A. Feature Overview**

Sellers upload a PDF/image certificate. Buyers see a "Verified ✓" badge. Admins verify/reject.

**B. Frontend Surface Area**

- **Seller auction edit** — "Authenticity Certificate" section with FilePond single-file upload. Status badge. Notes from admin.
- **Auction detail (public)** — "Authenticity Verified ✓" block with "View Certificate" button.
- **Auction detail (public, uploaded not verified)** — "Pending Verification" amber badge.
- **Admin auction detail** — verification UI with approve/reject buttons + notes textarea.
- **Auction index/browse** — "Authenticated" filter checkbox (already added to `auctions/index.blade.php`).

The `authCertManager()` Alpine component already exists in `seller/auctions/edit.blade.php`. The public detail already shows the cert block. The admin detail already has the verification UI. These are implemented — verify wire-up is complete.

**C. UX Flow**

- **Upload**: FilePond single-file. On select → immediate preview thumbnail. "Upload Certificate" button required.
- **After upload**: status badge changes to "Pending verification" (amber).
- **After admin verify**: badge changes to "Verified ✓" (green). Seller receives notification.
- **After admin reject**: badge "Rejected ✗" (red). Notes displayed.
- **Delete certificate**: confirmation → removes file and resets status.

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 92 — Product Comparison Tool

**A. Feature Overview**

Users select up to 4 auctions, compare side-by-side in a table. Prices poll every 30s.

**B. Frontend Surface Area**

- **Auction cards** — "Compare" checkbox (already in `<x-auction.card>`).
- **Floating compare bar** — fixed bottom tray showing selected auctions (already in `auctions/partials/compare-bar.blade.php`).
- **Comparison page** (`/auctions/compare`) — sticky-header table, per-auction columns, attribute rows (already in `auctions/compare.blade.php`).

These views are fully implemented. Verify the following work correctly:

- `AuctionCompareUI` singleton in `auctions/partials/compare-script.blade.php` ✓
- `compare-bar.blade.php` Alpine component ✓
- `compare.blade.php` table with sticky header ✓
- 30s price polling via `pollComparedAuctions()` ✓

**C. Remaining Gap**

The backend `AuctionComparisonController::compare()` needs to be wired. The frontend is ready; confirm GET/POST endpoint returns `attribute_columns` + `auctions` shape.

**D. Complexity & Priority**

- **Complexity**: Low (backend done, frontend done)
- **Priority**: Growth

---

#### Feature 96 — Category Commission Rates

**A. Feature Overview**

Category-specific platform fees. Shown informatively to sellers during auction creation.

**B. Frontend Surface Area**

- **Admin category create/edit** — commission rate field (already in `admin/categories/create.blade.php` and `edit.blade.php`).
- **Seller auction create** — commission hint below category selector (already partially in `seller/auctions/create.blade.php` via `fetchCommission()`).

The commission fetch is already wired in the seller create form:

```javascript
const response = await fetch(`/api/categories/${primaryId}/commission`, ...);
commissionHint.textContent = `Platform commission: ${data.commission_pct}%`;
```

**C. Remaining Gap**

Ensure the endpoint `/api/categories/{id}/commission` is registered. Update admin category edit form to show "Effective commission: X%" (already in the edit view).

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 97 — Featured Categories on Homepage

**A. Feature Overview**

Admin marks categories as featured. They appear on the homepage with custom banners, taglines, and live auction counts.

**B. Frontend Surface Area**

- **Homepage** (`welcome.blade.php`) — featured categories grid (already implemented).
- **Admin categories list** (`admin/categories/index.blade.php`) — feature management UI (already implemented with `categoryFeatureManager()` Alpine component).
- **Category show page** — featured badge if `$category->is_currently_featured`.

The homepage featured categories section and admin management are already fully implemented.

**C. Remaining Gap**

- Auto-expire badge in category show page.
- Scheduler running `unfeature-expired-categories`.

**D. Complexity & Priority**

- **Complexity**: Low
- **Priority**: Growth

---

### Section 5: Financials, Administration & Analytics

---

#### Feature 120 — Currency Conversion

**A. Feature Overview**

Users select display currency. All prices rendered via `format_price()`. Bids always submitted in USD.

**B. Frontend Surface Area**

- **Navbar** — currency selector (already in `layouts/navigation.blade.php`).
- **Welcome page** — currency selector (already in `welcome.blade.php`).
- **Auction detail** — "Display only. Bids are submitted in USD." note below price (already in `auctions/show.blade.php`).
- **Bid form** — minimum bid note shows display currency + USD equivalent (already in bid form).

All currency display is handled server-side via `format_price()`. The `formatDisplayPrice()` JS function handles client-side updates for real-time bid events (already in `auctions/show.blade.php`).

**C. Remaining Gap**

- The `formatDisplayPrice()` function needs to use the `displayRate` passed from Blade. Already implemented — verify the rate variable is always correctly injected.
- Add currency selector to mobile responsive menu (already present via `display-currency-mobile` select).

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 124 — Payout Schedule

**A. Feature Overview**

Sellers choose payout schedule (instant/weekly/bi-weekly/monthly). Pending payout balance shown in seller wallet/revenue.

**B. Frontend Surface Area**

- **Seller storefront settings** — new "Payout Settings" section: schedule selector + day picker.
- **Seller revenue/wallet** — "Pending Payout Balance: $X.XX" card.
- **Admin payout batches** — table of batch history.

**C. UX Flow**

Payout settings in `seller/storefront/edit.blade.php`:

```blade
<div x-data="{ schedule: '{{ $user->payout_schedule }}' }">
  <select name="payout_schedule" x-model="schedule">
    <option value="instant">Instant (as earned)</option>
    <option value="weekly">Weekly</option>
    <option value="biweekly">Bi-weekly</option>
    <option value="monthly">Monthly</option>
  </select>
  <div x-show="schedule === 'weekly' || schedule === 'biweekly'">
    Day: <select name="payout_schedule_day">Monday–Sunday options</select>
  </div>
  <div x-show="schedule === 'monthly'">
    Day of month: <input type="number" name="payout_schedule_day" min="1" max="28">
  </div>
</div>
```

**D. Complexity & Priority**

- **Complexity**: High
- **Priority**: Growth

---

#### Feature 138 — Live Chat Support Widget

**A. Feature Overview**

Floating chat widget. AI (Gemini) provides first-line support. "Talk to a human" escalates to admin.

**B. Frontend Surface Area**

- **All pages** — fixed bottom-right chat bubble (already in `<x-support-chat-widget>`).
- **Admin support inbox** (`admin/support/index.blade.php`) — already implemented.
- **Admin conversation detail** (`admin/support/show.blade.php`) — already implemented.

The `supportWidget()` Alpine component is fully implemented in `components/support-chat-widget.blade.php`. It handles:
- Message history rendering
- Send flow
- Escalation button
- Conversation persistence via `localStorage`

**C. Remaining Gap**

- Rate limiting: add client-side message counter. After 10 messages/hour, disable input with "Limit reached. Try again later."
- Typing indicator: show "AI is typing…" shimmer while awaiting response.

```javascript
// Add to supportWidget()
async send() {
  this.sending = true;
  // Show typing indicator
  this.messages.push({ role: 'assistant', body: '…', isTyping: true });
  const response = await fetch(...);
  this.messages = this.messages.filter(m => !m.isTyping);
  // ... rest of handler
}
```

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 162 — Admin Scheduled Maintenance Mode

**A. Feature Overview**

Admins schedule maintenance windows. Users see a countdown banner. Maintenance page served during downtime.

**B. Frontend Surface Area**

- **All pages** — maintenance announcement banner via `partials/maintenance-banner.blade.php` (already implemented with Alpine countdown).
- **Admin maintenance page** (`admin/maintenance/index.blade.php`) — already implemented.
- **`errors/maintenance.blade.php`** — already implemented with countdown timer.
- **Admin dashboard quick link** — "Maintenance Windows" card (already present).

All maintenance frontend is fully implemented.

**C. Remaining Gap**

- Add "Copy bypass URL" button to admin maintenance page:

```blade
<button @click="navigator.clipboard.writeText('{{ $bypassUrl }}')">Copy Bypass URL</button>
```

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Scaling

---

#### Features 169–172 — Analytics Reports

**A. Feature Overview**

Four analytics views: Category Performance, Peak Bidding Heatmap, Seller Leaderboard, Buyer Activity.

**B. Frontend Surface Area**

- **`admin/analytics/index.blade.php`** — already fully implemented with:
  - Category performance sortable table
  - 7×24 bid activity heatmap (intensity-color grid)
  - Seller leaderboard table
  - Buyer activity table
  - Period selector (7/30/90 days)
  - Refresh button

The view polls all four endpoints on load and on period change. All rendering is vanilla JS DOM manipulation in `@push('scripts')`.

**C. Remaining Gap**

- **Heatmap scroll on mobile**: the 7×24 grid overflows on small screens. Add `overflow-x-auto` wrapper.
- **Export buttons**: "Export CSV" for each report section (future enhancement).
- **Admin user detail** (`admin/users/show.blade.php`) — buyer analytics panel already implemented with 6-metric grid.

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth (169, 171), Scaling (170, 172)

---

### Section 6: Infrastructure, API & Testing

---

#### Feature 193 — Public REST API

**A. Feature Overview**

Versioned API at `/api/v1/`. Token management, rate-limited endpoints, Sanctum auth.

**B. Frontend Surface Area**

- **API token management page** (new) — `/dashboard/api-tokens` for authenticated users.
- **Swagger UI** (`/api/v1/documentation`) — handled by l5-swagger (already has vendor view).

**C. API Token Management UI**

New page: `user/api-tokens/index.blade.php`

```
┌─────────────────────────────────────┐
│ API Tokens                          │
│                                     │
│ Token Name: [device_name input]     │
│ Abilities: [checkboxes grid]        │
│ [Create Token]                      │
│                                     │
│ ── Active Tokens ──                 │
│ mobile-app  Created 3 days ago      │
│ Scopes: auctions:read, bids:read    │
│ [Revoke]                            │
└─────────────────────────────────────┘
```

On "Create Token" → response includes `plainTextToken` displayed once in a code block with "Copy" button + "This token will not be shown again" warning.

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Growth

---

#### Feature 194 — API Documentation

**A. Feature Overview**

Swagger UI served at `/api/v1/documentation`.

**B. Frontend Surface Area**

- Vendor view already in `vendor/l5-swagger/index.blade.php`.
- Add link to API docs in footer and developer settings page.

**C. Complexity & Priority**

- **Complexity**: Low
- **Priority**: Growth

---

#### Feature 195 — Webhook Support

**A. Feature Overview**

Sellers/integrators register HTTP endpoints. Admin can view delivery logs.

**B. Frontend Surface Area**

- **Developer settings page** (`/dashboard/webhooks`) — endpoint CRUD.
- **Webhook delivery history** — filterable table of past deliveries.
- **Admin webhook deliveries** — platform-wide delivery log.

**C. UX Flow**

```
┌──────────────────────────────────────────┐
│ Webhook Endpoints                        │
│                                          │
│ + Add Endpoint                           │
│                                          │
│ URL: [https://...input]                  │
│ Events: [☑ bid.placed] [☑ auction.closed]│
│ [Save]                                   │
│                                          │
│ ── Active Endpoints ──                   │
│ https://myapp.com/hooks                  │
│ Events: bid.placed, auction.closed        │
│ Last triggered: 2 hours ago              │
│ [Test] [Delete]                          │
└──────────────────────────────────────────┘
```

On "Test" → sends a test payload, shows response status inline.

Delivery log table: endpoint URL, event type, HTTP status, timestamp, [Re-deliver] button.

**D. Complexity & Priority**

- **Complexity**: High
- **Priority**: Growth

---

#### Feature 200 — Calendar Integration

**A. Feature Overview**

"Add to Calendar" dropdown on auction detail. Generates `.ics` download and Google Calendar link.

**B. Frontend Surface Area**

- **Auction detail** — "Add to Calendar" button with Alpine dropdown.

**C. Frontend Architecture**

```blade
<div x-data="{ open: false }" class="relative" @click.outside="open = false">
  <button @click="open = !open" class="flex items-center gap-2 text-sm border rounded-lg px-3 py-2">
    📅 Add to Calendar
    <svg ...chevron.../>
  </button>
  <div x-show="open" class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border z-10">
    <a href="{{ route('auctions.calendar.ics', $auction) }}" class="...">📥 Download .ics</a>
    <a href="{{ route('auctions.calendar.google', $auction) }}" target="_blank" class="...">📅 Google Calendar</a>
    <a href="{{ route('auctions.calendar.ics', $auction) }}" class="...">🍎 Apple Calendar</a>
  </div>
</div>
```

Show this button on active auctions only, in the countdown/timing area.

**D. Complexity & Priority**

- **Complexity**: Low
- **Priority**: Growth

---

#### Feature 203 — Elasticsearch Integration

**A. Feature Overview**

Replace SQL `ILIKE` search with Elasticsearch for relevance-ranked results. Graceful fallback to SQL.

**B. Frontend Surface Area**

- **Search form** (`auctions/index.blade.php`) — no change needed; the backend transparently switches between Elasticsearch and SQL.
- **Search results** — add relevance indicators (optional: "Best Match" sort option).
- **Loading state** — skeleton loaders while search results load.

**C. UX Enhancements with Elasticsearch**

- **Autocomplete** (future): `GET /api/v1/auctions?q=vintage&per_page=5` triggered on input with 300ms debounce.
- **"No results"** empty state: "No auctions match '{query}'. Try a broader search or browse [Categories]."
- **Search suggestions**: "Did you mean: [corrected term]?" (when Elasticsearch returns corrections).

**D. Complexity & Priority**

- **Complexity**: High
- **Priority**: Scaling

---

#### Feature 210 — Graceful Degradation (Redis Down → SQL)

**A. Feature Overview**

Automatic fallback to SQL bidding engine when Redis is unavailable. Frontend shows degraded-mode indicator.

**B. Frontend Surface Area**

- **Admin dashboard** — when `PessimisticSqlEngine` is active, show an alert banner: "⚠ Bidding engine degraded mode — Redis unavailable. Platform is operational with reduced performance."
- **Auction detail** — no UI change. Bidding continues normally.

**C. Frontend Architecture**

Add to `admin/dashboard.blade.php` — fetch degradation status from a new endpoint:

```javascript
async function checkEngineStatus() {
  const res = await fetch('/admin/metrics/live', ...);
  const data = await res.json();
  if (data.data?.engine_degraded) {
    // Show degradation banner
    document.getElementById('degradation-banner').classList.remove('hidden');
  }
}
```

**D. Complexity & Priority**

- **Complexity**: Medium
- **Priority**: Scaling

---

## 3. Shared Component Inventory

### Existing Components (Verify Coverage)

| Component | Location | Features That Use It |
|---|---|---|
| `<x-auction.card>` | `components/auction/card.blade.php` | BIN badge (7), Lot badge (14), Auth cert badge (90), Compare toggle (92), Featured badge (97) |
| `<x-ui.badge>` | `components/ui/badge.blade.php` | All status indicators |
| `<x-ui.button>` | `components/ui/button.blade.php` | All CTAs |
| `<x-ui.countdown>` | `components/ui/countdown.blade.php` | Auction cards, detail pages |
| `<x-ui.price>` | `components/ui/price.blade.php` | All price displays |
| `<x-ui.card>` | `components/ui/card.blade.php` | Dashboard panels |
| `<x-modal>` | `components/modal.blade.php` | Confirmation dialogs |
| `<x-notification-bell>` | `components/notification-bell.blade.php` | Navbar notifications |
| `<x-support-chat-widget>` | `components/support-chat-widget.blade.php` | All pages |
| `<x-skeleton.auction-card>` | `components/skeleton/auction-card.blade.php` | Loading states |

### New Components Required

| Component | Purpose | Features |
|---|---|---|
| `<x-auction.bin-button>` | Buy It Now UI | Feature 7 |
| `<x-auction.lot-items>` | Public lot item list | Feature 14 |
| `<x-auction.cert-badge>` | Auth cert verified/pending badge | Feature 90 |
| `<x-auction.calendar-dropdown>` | Add to calendar | Feature 200 |
| `<x-seller.vacation-card>` | Vacation mode dashboard widget | Feature 80 |
| `<x-seller.listing-fee-preview>` | Pre-publish fee preview | Feature 67 |
| `<x-user.referral-card>` | Referral link + stats | Feature 43 |
| `<x-user.api-token-card>` | API token display | Feature 193 |
| `<x-admin.webhook-endpoint-row>` | Webhook endpoint list item | Feature 195 |

### Shared Alpine Functions

| Function | File | Features |
|---|---|---|
| `binPurchase(auctionId, price)` | `js/bin-purchase.js` | Feature 7 |
| `autoSave(auctionId, url)` | `js/auto-save.js` | Feature 21 |
| `lotItemManager(auctionId)` | `js/lot-items.js` | Feature 14 |
| `followSeller(config)` | `js/follow-seller.js` | Feature 45 |
| `watchSettings(config)` | `js/watch-settings.js` | Feature 55 |
| `vacationMode(config)` | `js/vacation-mode.js` | Feature 80 |
| `listingFeePublish(config)` | `js/listing-fee-publish.js` | Feature 67 |

---

## 4. Route Map

### Public Routes (Guest)

```
/                              welcome.blade — Homepage with featured auctions + categories
/auctions                      auctions/index — Browse + filter + search
/auctions/{id}                 auctions/show — Auction detail (read-only for guests)
/auctions/compare              auctions/compare — Comparison table
/categories                    categories/index — Category browser
/categories/{slug}             categories/show — Category with filter sidebar
/storefront/{slug}             seller/storefront/show — Seller public page
/api/v1/documentation          Swagger UI
/api/v1/*                      Public REST API endpoints
/support/chat                  Support chat (POST, no auth required)
```

### Authenticated Buyer Routes

```
/dashboard                     user/dashboard — Active bids, watchlist, won items
/user/bids                     user/bid-history — Bid history with filters
/user/won-auctions             user/won-auctions — Won items by payment status
/user/watchlist                user/watchlist — Watched auctions
/user/wallet                   user/wallet — Balance, top-up, withdraw, history
/user/invoices                 user/invoices/index — Invoice list
/user/invoices/{id}            user/invoices/show — Invoice detail
/user/notification-preferences user/notification-preferences — Channel + threshold settings
/user/keyword-alerts           user/keyword-alerts — Alert management
/user/saved-searches           user/saved-searches — Saved filters
/user/referrals                user/referrals/index — Referral link + stats
/user/credits                  (new) Credits balance + power-up store
/user/api-tokens               (new) API token management
/dashboard/webhooks            (new) Webhook endpoint management
/dashboard/data-export         (new) GDPR data export request
/messages                      messages/index — Buyer message inbox
/messages/{id}                 messages/show — Conversation thread
/account/reactivate            (new) Account reactivation page
```

### Seller Routes (Verified Sellers)

```
/seller                        seller/dashboard — KPIs, live listings, revenue chart
/seller/auctions               seller/auctions/index — Auction management
/seller/auctions/create        seller/auctions/create — Create auction
/seller/auctions/import        seller/auctions/import — CSV import
/seller/auctions/schedule      seller/auctions/schedule — Calendar view
/seller/auctions/{id}/edit     seller/auctions/edit — Edit + manage images/cert
/seller/auctions/{id}/preview  (preview route) — Preview as buyer
/seller/messages               seller/messages/index — Buyer message inbox
/seller/messages/{id}          seller/messages/show — Thread
/seller/analytics              seller/analytics/index — Traffic + bid analytics
/seller/revenue                seller/revenue/index — Revenue reports + export
/seller/storefront/edit        seller/storefront/edit — Profile, bio, policy, avatar
/seller/tax-documents          seller/tax-documents/index — Tax PDF generation
/seller/auctions/{id}/insights seller/insights/show — Auction funnel analytics
/seller/vacation-mode          (new — form in dashboard, POST endpoint)
```

### Admin Routes

```
/admin                         admin/dashboard — Live metrics, fraud alerts, quick links
/admin/auctions                admin/auctions/index — Auction management + feature
/admin/auctions/{id}           admin/auctions/show — Detail + bid stats + actions
/admin/users                   admin/users/index — User management
/admin/users/{id}              admin/users/show — Profile + bids + buyer analytics + audit
/admin/reports                 admin/reports/index — Reported auctions queue
/admin/disputes                admin/disputes/index — Dispute queue
/admin/disputes/{id}           admin/disputes/show — Dispute detail + resolution
/admin/payments                admin/payments/index — Escrow holds + invoices
/admin/payments/transactions   admin/payments/transactions — Transaction log
/admin/audit-logs              admin/audit-logs/index — Admin action log
/admin/seller-applications     admin/seller-applications/index — Application queue
/admin/categories              admin/categories/index — Category + feature management
/admin/brands                  admin/brands/index — Brand management
/admin/attributes              admin/attributes/index — Attribute management
/admin/tags                    admin/tags/index — Tag management
/admin/analytics               admin/analytics/index — All 4 analytics reports
/admin/support                 admin/support/index — Support inbox
/admin/support/{id}            admin/support/show — Conversation + reply
/admin/maintenance             admin/maintenance/index — Maintenance window scheduler
/admin/bid-retractions         (new) Bid retraction request queue
/admin/listing-fees            (new) Listing fee tier CRUD
/api/v1/documentation          Swagger UI
```

---

## 5. Delivery Phases

### Phase 1 — Essential UX Foundation (Weeks 1–4)

**Goal**: Ensure every existing feature is pixel-perfect, accessible, and reliable.

**Frontend work:**
- [ ] Audit all existing Blade views against design tokens — replace any hardcoded colors
- [ ] Add `aria-*` attributes to bid form, countdown, price display, modal
- [ ] Add keyboard navigation to auction cards (Enter = navigate to detail)
- [ ] Implement `prefers-reduced-motion` for all animations
- [ ] Complete Feature 8 (Reserve toggle UI) — 1 day
- [ ] Complete Feature 22 (Preview mode banner) — 1 day
- [ ] Complete Feature 21 (Auto-save debounce) — 2 days
- [ ] Complete Feature 74 (Return policy form + display) — 1 day
- [ ] Complete Feature 58 (Deactivation UI) — 2 days
- [ ] Complete Feature 59 (GDPR export request) — 2 days
- [ ] Add skeleton loaders to all async-loaded sections

**Testing coverage:**
- Pest unit tests for all new Alpine functions
- Pest feature tests for all POST/PATCH endpoints
- Dusk E2E: bid placement, BIN purchase, watch toggle

---

### Phase 2 — Core Revenue & Productivity Features (Weeks 5–10)

**Goal**: Ship features that directly drive revenue and seller productivity.

**Frontend work:**
- [ ] Feature 7 — BIN button component + purchase flow
- [ ] Feature 67 — Listing fee preview + publish confirmation
- [ ] Feature 73 — Tax document page (view already exists, wire API)
- [ ] Feature 80 — Vacation mode dashboard card + buyer-facing banners
- [ ] Feature 43 — Referral page (view exists, wire to backend)
- [ ] Feature 45 — Follow seller toggle on storefronts
- [ ] Feature 55 — Watch threshold settings modal
- [ ] Feature 90 — Auth cert upload UI (already exists, verify wire-up)
- [ ] Feature 97 — Featured categories on homepage (already implemented)
- [ ] Feature 96 — Category commission hint in seller forms
- [ ] Feature 120 — Currency display verification + mobile selector
- [ ] Feature 138 — Support chat typing indicator + rate limit UX
- [ ] API token management page (Feature 193)
- [ ] Calendar dropdown on auction detail (Feature 200)

---

### Phase 3 — Growth & Analytics (Weeks 11–16)

**Goal**: Power user features, analytics views, and growth mechanics.

**Frontend work:**
- [ ] Feature 9 — Re-listing button + warning banner
- [ ] Feature 14 — Lot item manager (Alpine component + FilePond per item)
- [ ] Feature 46 — User block UI (three-dot menu + settings page)
- [ ] Feature 48 — Bid retraction request modal + admin queue
- [ ] Feature 50 — Credits balance + power-up store page
- [ ] Feature 56 — Locale selector in preferences
- [ ] Feature 124 — Payout schedule settings section
- [ ] Feature 195 — Webhook endpoint management page
- [ ] Analytics heatmap mobile scroll fix (Feature 170)
- [ ] Admin degradation banner (Feature 210)
- [ ] Admin webhook delivery log (Feature 195)

---

### Phase 4 — Scale & Platform Features (Weeks 17+)

**Goal**: Platform ecosystem features for high-volume operation.

**Frontend work:**
- [ ] Elasticsearch autocomplete on search input
- [ ] Feature 203 — Search no-results empty states + "did you mean?"
- [ ] API documentation navigation improvements
- [ ] Webhook test delivery UI
- [ ] Advanced admin analytics export buttons
- [ ] Seller leaderboard public page (optional)
- [ ] Power-up purchase flow with Stripe integration
- [ ] Referral dashboard enhancements

---

## 6. Testing Strategy

### Unit Tests (Pest)

```php
// resources/js/*.test.js equivalents via Pest feature tests

// Alpine state machine tests via browser:
test('binPurchase transitions idle → confirming → loading → success');
test('binPurchase reverts on 422 response');
test('autoSave debounces correctly and suppresses on non-draft');
test('lotItemManager add/remove/reorder updates local state');
test('followSeller optimistic toggle reverts on error');
test('watchSettings validates threshold values');
```

### Component Tests

For each Blade component, test render output with different prop combinations:

```php
test('<x-auction.bin-button> hides when available=false');
test('<x-auction.card> shows BIN badge when binAvailable');
test('<x-auction.card> shows lot badge with item count');
test('<x-auction.cert-badge> renders correct color per status');
test('<x-ui.countdown> shows "Ended" when past end_time');
```

### Integration Tests (Pest Feature)

```php
// BIN purchase flow
test('POST /auctions/{id}/buy-it-now returns 422 when BIN expired');
test('POST /auctions/{id}/buy-it-now redirects to invoice on success');

// Comparison
test('GET /auctions/compare returns attribute_columns aligned with auctions');

// Auto-save
test('PATCH /seller/auctions/{id}/auto-save only saves whitelisted fields');
test('PATCH /seller/auctions/{id}/auto-save 422s on non-draft auction');

// Calendar
test('GET /auctions/{id}/calendar.ics returns valid iCalendar');
test('GET /auctions/{id}/calendar/google returns Google Calendar URL');

// Referrals
test('GET /user/referrals shows referral link and table');
```

### E2E Scenarios (Laravel Dusk)

| Scenario | Priority |
|---|---|
| Buyer places bid via bid form | P0 |
| Buyer uses BIN to win auction | P0 |
| BIN button disappears when bid placed | P0 |
| Seller creates draft → auto-saves → publishes | P1 |
| Seller activates vacation mode → buyer sees banner | P1 |
| Seller creates lot auction with 3 items | P1 |
| User follows seller → unfollows | P2 |
| User requests bid retraction | P2 |
| Admin verifies auth cert → seller notified | P2 |
| User adds auction to Google Calendar | P3 |
| Currency switcher updates all prices | P3 |

### Edge Cases to Automate

- BIN purchase while concurrent bidder places bid (race condition display)
- Auto-save fires on every field independently (not just on submit)
- Comparison bar persists across page navigation (localStorage)
- Countdown timer shows "Ended" at exact expiry (not 1s late)
- Vacation mode blocks bid form without breaking page layout
- Reserve price hidden from guest but visible in source (must not leak)
- BIN form submission with expired session (401 redirect)

---

## 7. Quick Wins vs Complex Work

### Fast-to-Build (≤1 day each)

| Feature | Effort | Why Fast |
|---|---|---|
| Feature 8 — Reserve toggle UI | 2h | One checkbox + conditional display in existing template |
| Feature 22 — Preview mode banner | 2h | CSS banner + `$isPreview` flag already supported |
| Feature 200 — Calendar dropdown | 3h | Alpine dropdown + 2 static URL generators |
| Feature 74 — Return policy form | 4h | Radio buttons + conditional fields; already in storefront edit |
| Feature 97 — Featured categories (already done) | 0h | View fully implemented |
| Feature 92 — Comparison (already done) | 0h | Views and scripts fully implemented |

### Features Requiring Major UX/Design Work

| Feature | Why Complex |
|---|---|
| Feature 14 — Lot Item Manager | Dynamic CRUD with per-item FilePond, drag-to-reorder, inline editing |
| Feature 50 — Power-Ups Store | Gamification UI requires custom illustrations/icons + purchase flow |
| Feature 48 — Bid Retraction | Dual-role UI (user request + admin queue + price recalculation feedback) |
| Feature 203 — Elasticsearch UX | Autocomplete, relevance signals, faceted filters — significant JS work |
| Feature 195 — Webhook Manager | Technical UI needing delivery log, test send, HMAC instructions |

### Features With Highest Backend Coordination Cost

| Feature | Coordination Needed |
|---|---|
| Feature 7 — BIN Purchase | Real-time BIN expiry via Echo; invoice redirect; escrow edge cases |
| Feature 124 — Payout Schedule | Pending balance display depends on new `pending_payout_balance` column |
| Feature 48 — Bid Retraction | Price recalculation + Redis sync must be confirmed before UI can reflect new price |
| Feature 193 — Public API | Token management UI needs abilities system finalized |
| Feature 195 — Webhooks | SSRF validation on URL input must be server-side; frontend just submits |

---

## 8. Risks & Recommendations

### Risk 1: Real-Time BIN Expiry Race Condition (UX)

**Risk**: BIN button visible when auction has active bids above threshold — user clicks, gets 422.
**Mitigation**: The `liveState()` 4s polling + Echo `bid.placed` listener both hide the BIN button. However, between events, the button may still appear.
**Recommendation**: On BIN 422, immediately hide button via Alpine state and display clear error. Add `isBuyItNowAvailable` to every `liveState()` response.

### Risk 2: Auto-Save Triggers on Published Auctions

**Risk**: If user navigates to edit a published auction, auto-save fires and gets 422.
**Mitigation**: Server rejects non-draft auction patches. Frontend: check response for 422 with `"Only drafts can be auto-saved."` message and suppress future auto-save calls + show indicator "Auto-save unavailable for live auctions."

### Risk 3: Currency Display Inconsistency on WebSocket Updates

**Risk**: When a bid arrives via WebSocket, the new price is displayed by `formatDisplayPrice()` JS function. If the exchange rate has changed since page load, the displayed amount may be slightly off.
**Mitigation**: Acceptable tradeoff — rates are cached for 1h. Add note "Display prices are approximate. Bid in USD." Already present in auction detail.

### Risk 4: Lot Item Manager Performance

**Risk**: 50 lot items with per-item FilePond instances may cause memory issues.
**Mitigation**: Lazy-initialize FilePond only when image upload is triggered per item. Destroy FilePond instance after upload. Use `preserving_original()` on server side.

### Risk 5: Mobile Accessibility of Complex UIs

**Risk**: Comparison table, analytics heatmap, and lot item manager are desktop-first.
**Mitigation**:
- Comparison table: horizontal scroll on mobile with sticky first column.
- Heatmap: `overflow-x-auto` wrapper, horizontal scroll.
- Lot item manager: stack layout on mobile, accordion-style editing.
- All touch targets: minimum 44×44px per existing `tokens.css` note.

### Risk 6: Permission Leakage via API

**Risk**: `reserve_price` leaked in HTML source even when `reserve_price_visible === false`.
**Mitigation**: Never pass `reserve_price` to Blade views when hidden. Only pass `reserve_price !== null` boolean. Server-side `AuctionResource` handles this correctly; verify Blade templates don't expose it directly.

### Risk 7: Elasticsearch Unavailability During Deployment

**Risk**: If Elasticsearch is down or not yet provisioned, search page breaks.
**Mitigation**: `try/catch` in `AuctionController::index()` falls back to SQL (already planned). Frontend: no change needed — results look identical. Only latency difference.

### Recommended Frontend Architecture Improvements

1. **Extract `bidPanel.js`**: The bid form, auto-bid, and countdown logic in `auctions/show.blade.php` is 200+ lines of inline scripts. Move to `resources/js/bid-panel.js` as an Alpine component factory for testability.

2. **`resources/js/auction-realtime.js`**: Consolidate the scattered `window.addEventListener('bid:placed', ...)` handlers and `syncLiveState()` polling into a single module. Currently split across inline scripts in `show.blade.php`.

3. **Design token enforcement**: Run a CSS audit script in CI to flag any hardcoded hex values in Blade templates not using CSS variables.

4. **Alpine component registration**: Move all Alpine function definitions from inline `<script>` tags in Blade templates into importable JS modules. This enables unit testing without a browser.

5. **Standardize loading states**: Create an `<x-loading-overlay>` component and use it consistently across all async operations (bid placement, BIN purchase, follow toggle). Currently each feature re-implements its own.

---

*Document generated: 2026-04-22. Covers 40 features across 6 sections. Aligns with Laravel 12 / Alpine.js / Tailwind CSS / Reverb stack as evidenced by existing codebase.*