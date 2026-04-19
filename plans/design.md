# BidFlow — Full Redesign Project Plan
### Agent-Executable Micro-Task Breakdown

> **How to use this plan**
> Each micro-task contains five sections:
> - **Goal** — what must be true when this task is done
> - **Agent context** — paste this block verbatim as the first message to your agent
> - **Acceptance criteria** — checklist the agent verifies before committing
> - **Test command** — run this to confirm correctness
> - **Commit message** — use this exact string for the git commit

---

## PHASE 1 — Foundation & Design System
> Establish shared tokens, components, and infrastructure everything else builds on.
> No visible user-facing changes yet. Safe to ship to production immediately.
> **Estimated micro-tasks:** 12
> **Agent parallelism:** Tasks 1.1–1.4 can run simultaneously. 1.5–1.8 depend on 1.1.

---

### 1.1 — Install & configure design token CSS variables

**Goal:** A single `resources/css/tokens.css` file that every other stylesheet imports. Defines all colors, spacing, radius, shadow, and typography tokens. Works in both light and dark mode.

**Agent context:**
```
You are working on a Laravel 11 + Tailwind CSS + Alpine.js project called BidFlow (an auction platform).

TASK: Create `resources/css/tokens.css` with CSS custom properties for the design system.

Requirements:
1. Define these color tokens (each needs a light and dark-mode value via @media prefers-color-scheme):
   - --color-primary: indigo-600 / indigo-400
   - --color-primary-hover: indigo-700 / indigo-300
   - --color-surface: white / gray-900
   - --color-surface-raised: white / gray-800
   - --color-surface-sunken: gray-50 / gray-950
   - --color-border: rgba(0,0,0,0.08) / rgba(255,255,255,0.08)
   - --color-text-primary: gray-900 / gray-50
   - --color-text-secondary: gray-500 / gray-400
   - --color-text-muted: gray-400 / gray-600
   - --color-success: green-600 / green-400
   - --color-danger: red-600 / red-400
   - --color-warning: amber-500 / amber-400
   - --color-info: blue-600 / blue-400
   - --color-bid-live: green-500 (same both modes, this is a brand colour)
   - --color-snipe: orange-500 (same both modes)

2. Define spacing tokens: --space-1 through --space-16 (4px increments: 4,8,12,16,20,24,32,40,48,64px)

3. Define radius tokens: --radius-sm:4px --radius-md:8px --radius-lg:12px --radius-xl:20px --radius-full:9999px

4. Define shadow tokens: --shadow-sm, --shadow-md, --shadow-lg using box-shadow

5. Define typography tokens: --font-sans, --font-mono, --text-xs through --text-2xl (font-size), --leading-tight, --leading-normal, --leading-relaxed

Then update `resources/css/app.css` to @import this file FIRST before Tailwind directives.

Output ONLY the file contents. Do not add any explanation.
```

**Acceptance criteria:**
- [ ] `resources/css/tokens.css` exists
- [ ] All tokens defined with fallback values
- [ ] Dark-mode block uses `@media (prefers-color-scheme: dark)`
- [ ] `resources/css/app.css` imports tokens before `@tailwind base`
- [ ] `npm run build` produces no errors

**Test command:** `npm run build && grep -c "var(--color" resources/css/tokens.css`

**Commit message:** `design: add CSS token system with light/dark mode variables`

---

### 1.2 — Create Blade component: `<x-ui-button>`

**Goal:** A polymorphic button component replacing all raw `<button>` and `<a class="...btn">` usages. Supports variants, sizes, loading state, icon slots.

**Agent context:**
```
You are working on a Laravel 11 Blade component system for BidFlow (auction platform).

TASK: Create `resources/views/components/ui/button.blade.php` and its class `app/View/Components/Ui/Button.php`.

The component must support:
- $variant: 'primary' | 'secondary' | 'danger' | 'ghost' (default: 'primary')
- $size: 'sm' | 'md' | 'lg' (default: 'md')
- $loading: bool (default: false) — shows spinner, disables button
- $href: string|null — if set, renders as <a> tag instead of <button>
- $type: string (default: 'button')
- $disabled: bool (default: false)
- slot: button label
- $icon: string|null — optional leading SVG path data (24x24 viewBox)

Variant styles (use Tailwind classes, no custom CSS):
- primary: bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:ring-2 ring-indigo-500
- secondary: bg-white text-gray-700 border border-gray-200 hover:bg-gray-50
- danger: bg-red-600 text-white hover:bg-red-700
- ghost: text-gray-600 hover:bg-gray-100

Size styles:
- sm: text-xs px-3 py-1.5 rounded-md gap-1.5
- md: text-sm px-4 py-2 rounded-lg gap-2
- lg: text-base px-6 py-3 rounded-xl gap-2.5

Loading state: show a 16px SVG spinner (animate-spin) before the slot. Disable pointer-events.

The component class should use PHP 8.2 constructor property promotion.

Usage example that must work:
  <x-ui-button variant="primary" size="md" :loading="$saving">Save Changes</x-ui-button>
  <x-ui-button variant="secondary" href="{{ route('auctions.index') }}">Browse</x-ui-button>
  <x-ui-button variant="danger" :disabled="$auction->isActive()">Delete</x-ui-button>

Output both files completely.
```

**Acceptance criteria:**
- [ ] Component renders as `<a>` when `$href` is provided
- [ ] Loading state shows spinner and sets `aria-busy="true"`
- [ ] All variants apply correct Tailwind classes
- [ ] `disabled` attribute is forwarded to underlying element
- [ ] `php artisan view:clear` runs without error

**Test command:** `php artisan view:clear && php artisan component:make --test Ui/Button 2>/dev/null || echo "component ok"`

**Commit message:** `feat(ui): add polymorphic x-ui-button component with variants and loading state`

---

### 1.3 — Create Blade component: `<x-ui-badge>`

**Goal:** Consistent badge/pill component used for auction status, bid type labels, seller badges, etc.

**Agent context:**
```
You are working on a Laravel 11 Blade component system for BidFlow (auction platform).

TASK: Create `resources/views/components/ui/badge.blade.php`.

Props:
- $color: 'green' | 'red' | 'blue' | 'amber' | 'gray' | 'indigo' | 'orange' (default: 'gray')
- $size: 'xs' | 'sm' (default: 'sm')
- $dot: bool (default: false) — prepend a colored dot (live indicator)
- $pulse: bool (default: false) — animate the dot with animate-ping

Color map (background + text, using Tailwind):
- green:  bg-green-100  text-green-800  dark:bg-green-900  dark:text-green-300
- red:    bg-red-100    text-red-800    dark:bg-red-900    dark:text-red-300
- blue:   bg-blue-100   text-blue-800   dark:bg-blue-900   dark:text-blue-300
- amber:  bg-amber-100  text-amber-800  dark:bg-amber-900  dark:text-amber-300
- gray:   bg-gray-100   text-gray-700   dark:bg-gray-700   dark:text-gray-300
- indigo: bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300
- orange: bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300

Usage examples that must work:
  <x-ui-badge color="green" :dot="true" :pulse="true">Live</x-ui-badge>
  <x-ui-badge color="red">Outbid</x-ui-badge>
  <x-ui-badge color="amber" size="xs">Reserve not met</x-ui-badge>

No PHP class needed — use anonymous component with @props.
```

**Acceptance criteria:**
- [ ] All 7 colors render correct Tailwind classes
- [ ] Pulse dot uses `relative` wrapper with `animate-ping` overlay
- [ ] Dark mode classes present on every color variant
- [ ] Component works without `$dot` prop (defaults to no dot)

**Test command:** `grep -c "dark:" resources/views/components/ui/badge.blade.php`

**Commit message:** `feat(ui): add x-ui-badge component with 7 colors and live dot indicator`

---

### 1.4 — Create Blade component: `<x-ui-card>`

**Goal:** Standard card wrapper used throughout the app. Replaces all `bg-white overflow-hidden shadow-sm sm:rounded-lg` repetition.

**Agent context:**
```
TASK: Create `resources/views/components/ui/card.blade.php` for BidFlow.

Props:
- $padding: 'none' | 'sm' | 'md' | 'lg' (default: 'md')
- $hover: bool (default: false) — adds hover:shadow-md transition
- $border: bool (default: true)

Named slots:
- $header (optional) — rendered above content with border-bottom
- $footer (optional) — rendered below content with border-top
- default slot — main content

Padding map: none=p-0, sm=p-4, md=p-6, lg=p-8

The card base classes: bg-white dark:bg-gray-800 rounded-xl overflow-hidden
When $border: border border-gray-200 dark:border-gray-700
When $hover: hover:shadow-md transition-shadow duration-200 cursor-pointer

Header slot: renders in a div with px-6 py-4 border-b border-gray-200 dark:border-gray-700
Footer slot: renders in a div with px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900

Usage that must work:
  <x-ui-card>Simple card content</x-ui-card>

  <x-ui-card :hover="true">
    <x-slot:header>Card Title</x-slot:header>
    Body content here
    <x-slot:footer>Footer actions</x-slot:footer>
  </x-ui-card>

  <x-ui-card padding="none" :border="false">No padding, no border</x-ui-card>
```

**Acceptance criteria:**
- [ ] Header and footer slots only render when provided
- [ ] Padding classes applied to correct div (the body, not the wrapper)
- [ ] Dark mode classes on bg, border, header, footer
- [ ] `hover` variant adds Tailwind transition classes

**Test command:** `php artisan view:clear && echo "card component ok"`

**Commit message:** `feat(ui): add x-ui-card component with header/footer slots and hover state`

---

### 1.5 — Create Blade component: `<x-ui-countdown>`

**Goal:** Reusable Alpine.js countdown timer component used on auction cards, detail pages, and dashboard. Replaces duplicated countdown JS across multiple files.

**Agent context:**
```
You are working on a Laravel 11 + Alpine.js project called BidFlow.

TASK: Create `resources/views/components/ui/countdown.blade.php`.

This component renders a live countdown timer using Alpine.js.

Props:
- $endsAt: string — ISO 8601 datetime string (e.g. "2025-12-31T23:59:59Z")
- $snipeThreshold: int (default: 30) — seconds remaining when snipe warning activates
- $size: 'sm' | 'md' | 'lg' (default: 'md')
- $showLabel: bool (default: true) — show "Time remaining" label above

The component uses x-data with an Alpine component that:
1. Calculates diff = endTime - Date.now() every second using setInterval
2. Exposes: days, hours, minutes, seconds, isEnded, isSnipeWarning (diff/1000 <= snipeThreshold)
3. Formats each unit with leading zero padding
4. Clears interval when diff <= 0

Display modes:
- Normal: text-gray-800 dark:text-gray-100
- Snipe warning (isSnipeWarning && !isEnded): text-orange-600 animate-pulse
- Ended: show "Ended" in text-gray-400

Size map for the time number text:
- sm: text-sm font-mono
- md: text-lg font-mono font-semibold
- lg: text-3xl font-mono font-bold

Show days only when days > 0.
Show hours only when days > 0 or hours > 0.
Always show minutes and seconds.

Format: "2d 14h 32m 09s" or "45m 09s" or "Ended"

The component must expose a window.updateAuctionEndTime(newIsoString) function via x-init so WebSocket events can extend the timer.

Usage:
  <x-ui-countdown :ends-at="$auction->end_time->toIso8601String()" :snipe-threshold="30" size="md" />
```

**Acceptance criteria:**
- [ ] Component updates every second without memory leak (interval cleared on destroy)
- [ ] `isSnipeWarning` activates at correct threshold
- [ ] `window.updateAuctionEndTime` is set in x-init
- [ ] Days segment hidden when 0
- [ ] "Ended" state shown correctly
- [ ] No hardcoded JS in the Blade file outside `x-data`

**Test command:** `grep -c "clearInterval\|setInterval" resources/views/components/ui/countdown.blade.php`

**Commit message:** `feat(ui): add x-ui-countdown Alpine component with snipe warning and extension support`

---

### 1.6 — Create Blade component: `<x-ui-price>`

**Goal:** Consistent price display component used on cards and detail pages. Handles currency formatting, increment animations, and "current price" vs "starting price" semantics.

**Agent context:**
```
TASK: Create `resources/views/components/ui/price.blade.php` for BidFlow.

Props:
- $amount: float|int
- $currency: string (default: 'USD')
- $size: 'sm' | 'md' | 'lg' | 'xl' (default: 'md')
- $label: string|null — optional small label above (e.g. "Current bid")
- $animate: bool (default: false) — when true, wrap in Alpine x-data that listens for a 'price-updated' CustomEvent on window and briefly flashes yellow then fades back
- $id: string|null — HTML id for JS targeting

Size map (for the number text):
- sm: text-base font-semibold text-green-600
- md: text-2xl font-bold text-green-600
- lg: text-4xl font-black text-green-600
- xl: text-6xl font-black text-green-600 tabular-nums

Label: text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5

Format using PHP's number_format with 2 decimal places.
Prepend the currency symbol: USD=$, EUR=€, GBP=£, default to $ for unknown.

When $animate is true, add Alpine.js:
  x-data="{ flash: false }"
  x-init="window.addEventListener('price-updated', (e) => { if (!id || e.detail.id === id) { flash = true; setTimeout(() => flash = false, 600) } })"
  :class="flash ? 'text-amber-500 scale-105 transition-all' : 'text-green-600 transition-all'"

Usage:
  <x-ui-price :amount="450.00" label="Current bid" size="lg" :animate="true" id="main-price" />
  <x-ui-price :amount="100.00" size="sm" />
```

**Acceptance criteria:**
- [ ] USD, EUR, GBP symbols render correctly
- [ ] Unknown currency falls back to `$`
- [ ] `animate` prop adds Alpine attributes
- [ ] Label renders above amount when provided
- [ ] Number always formatted to 2 decimal places

**Test command:** `php artisan view:clear && echo "price component ok"`

**Commit message:** `feat(ui): add x-ui-price component with currency formatting and flash animation`

---

### 1.7 — Create global Blade layout improvements

**Goal:** Update `resources/views/layouts/app.blade.php` to include dark mode class toggle, toast notification system, command palette placeholder, and stack for page-level meta.

**Agent context:**
```
You are modifying `resources/views/layouts/app.blade.php` in a Laravel 11 project (BidFlow auction platform).

CURRENT FILE: The existing app.blade.php already has navigation, Vite assets, @stack('scripts'). Do NOT remove any existing functionality.

CHANGES TO MAKE:

1. Add `class="antialiased"` to the <html> tag and add Alpine.js dark mode support:
   Replace: <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
   With:    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data x-bind:class="{ 'dark': $store.theme.isDark }">

2. Add a <meta name="theme-color"> tag in <head>.

3. Add a @stack('head') in <head> before closing </head> for page-level meta/styles.

4. Add a global toast container INSIDE <body> but OUTSIDE the main content div:
   - Position: fixed bottom-4 right-4 z-50
   - Uses Alpine.js x-data with a toasts array
   - Listens to window 'toast' CustomEvent: window.addEventListener('toast', e => this.toasts.push({id: Date.now(), ...e.detail}))
   - Each toast auto-removes after 4000ms
   - Toast types: 'success' (green), 'error' (red), 'info' (blue), 'warning' (amber)
   - Transition: x-transition with enter from bottom + fade
   - Each toast shows an icon, message, and an X close button
   - Max 5 toasts visible (splice oldest when over limit)

5. Add a global Alpine store initialization BEFORE closing </body>:
   <script>
   document.addEventListener('alpine:init', () => {
     Alpine.store('theme', {
       isDark: localStorage.getItem('theme') === 'dark' || 
               (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
       toggle() {
         this.isDark = !this.isDark;
         localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
       }
     });
   });
   </script>

6. Add a @stack('modals') before closing </body>.

Output the COMPLETE modified app.blade.php file.
```

**Acceptance criteria:**
- [ ] `<html>` tag has Alpine dark mode binding
- [ ] Toast container present and uses `x-transition`
- [ ] Alpine store for theme initialized
- [ ] `@stack('head')` and `@stack('modals')` added
- [ ] Existing navigation and @stack('scripts') preserved
- [ ] No duplicate Vite asset includes

**Test command:** `grep -c "Alpine.store\|@stack('modals')\|toast" resources/views/layouts/app.blade.php`

**Commit message:** `feat(layout): add dark mode store, toast system, and layout stacks to app layout`

---

### 1.8 — Create JavaScript utility: toast helper

**Goal:** A `window.toast()` helper function in `resources/js/toast.js` so any JS file can trigger toasts without Alpine coupling.

**Agent context:**
```
TASK: Create `resources/js/toast.js` for BidFlow.

This file must export and attach to window a toast() function.

window.toast = function(message, type = 'info', duration = 4000) {
  // Dispatch a CustomEvent named 'toast' on window
  // Payload: { message, type, duration }
  // type is one of: 'success' | 'error' | 'info' | 'warning'
  window.dispatchEvent(new CustomEvent('toast', {
    detail: { message, type, duration }
  }));
};

// Convenience aliases
window.toast.success = (msg, dur) => window.toast(msg, 'success', dur);
window.toast.error   = (msg, dur) => window.toast(msg, 'error', dur);
window.toast.info    = (msg, dur) => window.toast(msg, 'info', dur);
window.toast.warning = (msg, dur) => window.toast(msg, 'warning', dur);

Also import this file in `resources/js/app.js` so it's globally available.

Then update any fetch() call in existing Blade files that currently shows alert() on success/error
to instead call window.toast.success() or window.toast.error().

Specifically update these views:
- resources/views/admin/auctions/show.blade.php (force-cancel and extend forms)
- resources/views/admin/users/show.blade.php (ban, unban, changeRole functions)
- resources/views/admin/reports/index.blade.php (reviewReport function)

In each file, replace:
  alert(data.message)  →  window.toast.success(data.message)  (on resp.ok)
  alert(data.message || '...')  →  window.toast.error(data.message || '...')  (on error)
  alert('Request failed.')  →  window.toast.error('Request failed.')

Output: toast.js full content, app.js import line, and all three updated view snippets showing only the changed lines with line context.
```

**Acceptance criteria:**
- [ ] `window.toast`, `window.toast.success`, `.error`, `.info`, `.warning` all defined
- [ ] Imported in `app.js`
- [ ] All three admin views updated (no bare `alert()` calls remaining)
- [ ] CustomEvent dispatched with correct shape

**Test command:** `grep -rn "alert(" resources/views/admin/ | grep -v "//"`

**Commit message:** `feat(js): add global toast helper, replace alert() calls in admin views`

---

### 1.9 — Create JavaScript utility: bid event bus

**Goal:** A central `BidEventBus` in `resources/js/bid-events.js` that decouples WebSocket events from UI components. Everything listens to this bus rather than directly to Echo.

**Agent context:**
```
TASK: Create `resources/js/bid-events.js` for BidFlow.

This module must export a singleton BidEventBus and attach it to window.

The bus wraps Echo channel subscriptions and dispatches CustomEvents:

class BidEventBus {
  constructor() {
    this._channels = new Map();
  }

  subscribe(auctionId) {
    if (this._channels.has(auctionId)) return;

    const channel = window.Echo.channel(`auctions.${auctionId}`);
    this._channels.set(auctionId, channel);

    channel.listen('.bid.placed', (e) => {
      window.dispatchEvent(new CustomEvent('bid:placed', { detail: { auctionId, ...e } }));
    });

    channel.listen('.price-updated', (e) => {
      window.dispatchEvent(new CustomEvent('price:updated', { detail: { auctionId, newPrice: e.newPrice } }));
    });

    channel.listen('.auction.closed', (e) => {
      window.dispatchEvent(new CustomEvent('auction:closed', { detail: { auctionId, ...e } }));
    });
  }

  unsubscribe(auctionId) {
    if (this._channels.has(auctionId)) {
      window.Echo.leaveChannel(`auctions.${auctionId}`);
      this._channels.delete(auctionId);
    }
  }

  unsubscribeAll() {
    this._channels.forEach((_, id) => this.unsubscribe(id));
  }
}

window.BidEventBus = new BidEventBus();

Import this in app.js AFTER Echo is initialized.

Also create a corresponding `resources/js/bid-ui.js` that listens to these CustomEvents and updates the DOM:
- 'bid:placed' → update #price-display, #min-bid, #bid-amount, #bid-count, #highest-bidder if present on the page, prepend to #bid-history
- 'price:updated' → update #price-display if present
- 'auction:closed' → disable #bid-form if present, update #countdown to "Ended"

This separates Echo from DOM manipulation. The existing inline <script type="module"> in auctions/show.blade.php should be REPLACED with:
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      window.BidEventBus.subscribe({{ $auction->id }});
    });
  </script>

Output: bid-events.js, bid-ui.js, the replacement script block for auctions/show.blade.php.
```

**Acceptance criteria:**
- [ ] `BidEventBus.subscribe()` idempotent (calling twice doesn't double-subscribe)
- [ ] `unsubscribeAll()` cleans up all channels
- [ ] `bid-ui.js` guards every DOM operation with null checks
- [ ] `auctions/show.blade.php` no longer has inline Echo channel calls

**Test command:** `grep -c "Echo.channel" resources/views/auctions/show.blade.php`

**Commit message:** `feat(js): add BidEventBus and bid-ui event-driven architecture`

---

### 1.10 — Database migration: add `is_featured` and `views_count` to auctions

**Goal:** Add missing columns needed by the new homepage and analytics features. Safe migration with defaults.

**Agent context:**
```
You are adding database columns to an existing Laravel 11 project with PostgreSQL (BidFlow auction platform).

The `auctions` table already exists. Create a new migration that adds:

1. `views_count` integer NOT NULL DEFAULT 0 — total page views
2. `unique_viewers_count` integer NOT NULL DEFAULT 0 — unique visitors (tracked by session)
3. `is_featured` boolean NOT NULL DEFAULT false — admin can feature an auction on homepage
4. `featured_until` timestamp nullable — when feature expires
5. `featured_position` smallint nullable — ordering within featured section (1=top)

Migration file: database/migrations/YYYY_MM_DD_000001_add_featured_and_analytics_to_auctions_table.php

Also update `app/Models/Auction.php`:
- Add these fields to $fillable array
- Add a scope: scopeFeatured(Builder $query) that filters is_featured=true AND (featured_until IS NULL OR featured_until > now())
- Add a scope: scopePopular(Builder $query) that orders by (bids_count + views_count * 0.1) DESC
- Add an accessor: getIsCurrentlyFeaturedAttribute() that checks both is_featured and featured_until

Output: the migration file and the relevant additions to Auction.php (show only changed sections with context).
```

**Acceptance criteria:**
- [ ] Migration runs without error: `php artisan migrate --pretend`
- [ ] `scopeFeatured` handles NULL `featured_until` correctly
- [ ] `$fillable` includes all new fields
- [ ] Migration is reversible (has `down()` method)

**Test command:** `php artisan migrate --pretend 2>&1 | grep -i "error" || echo "migration ok"`

**Commit message:** `feat(db): add featured auction and analytics columns to auctions table`

---

### 1.11 — Database migration: add user preferences table

**Goal:** Store per-user preferences (theme, notification settings, bid increments) in a dedicated JSON column rather than spreading them across the users table.

**Agent context:**
```
TASK: Create a migration and model for user preferences in BidFlow (Laravel 11, PostgreSQL).

Migration: database/migrations/YYYY_MM_DD_000002_create_user_preferences_table.php

Table: user_preferences
Columns:
- id: bigint primary key
- user_id: bigint NOT NULL UNIQUE (foreign key → users.id, cascade delete)
- theme: varchar(10) DEFAULT 'system' (values: 'light' | 'dark' | 'system')
- bid_increment_preference: varchar(20) DEFAULT 'minimum' (values: 'minimum' | 'plus5pct' | 'plus10pct' | 'custom')
- custom_increment_amount: decimal(10,2) nullable
- notification_email: json NOT NULL DEFAULT '{}' — stores per-event boolean map
- notification_push: json NOT NULL DEFAULT '{}'
- notification_database: json NOT NULL DEFAULT '{}'
- show_bid_history_names: boolean DEFAULT true — whether to show real names in bid history
- watchlist_email_digest: varchar(10) DEFAULT 'daily' (values: 'never' | 'daily' | 'weekly')
- timezone: varchar(50) DEFAULT 'UTC'
- created_at / updated_at timestamps

Create model: app/Models/UserPreference.php
- belongsTo User
- Cast json columns to array
- Add static method: forUser(int $userId) — returns existing or creates with defaults

Update User model: add hasOne UserPreference, add getPreferencesAttribute() shortcut.

Output both files completely.
```

**Acceptance criteria:**
- [ ] `sail artisan migrate --pretend` runs clean
- [ ] `UserPreference::forUser($id)` creates record with defaults if missing
- [ ] JSON casts defined for all three notification columns
- [ ] `user_id` column has UNIQUE constraint

**Test command:** `sail artisan migrate --pretend 2>&1 | tail -5`

**Commit message:** `feat(db): add user_preferences table with theme, notification, and bid settings`

---

### 1.12 — Middleware: track auction views

**Goal:** Replace the placeholder `track.auction.view` middleware with a real implementation that increments `views_count` and tracks unique viewers via session, throttled to once per hour per user per auction.

**Agent context:**
```
TASK: Implement `app/Http/Middleware/TrackAuctionView.php` in BidFlow (Laravel 11).

The middleware is already registered in routes/web.php as 'track.auction.view' on the auctions.show route.

Current implementation is a stub. Replace with:

1. Extract auction from route parameter ($request->route('auction'))
2. Build a cache key: "auction_view_{$auction->id}_user_{$userId_or_ip}"
   - For authenticated users: use auth()->id()
   - For guests: use sha256 of IP + User-Agent (privacy-safe)
3. If cache key does NOT exist (i.e., this is a new view within the throttle window):
   a. Set cache key with TTL = 3600 (1 hour), value = 1
   b. Dispatch a queued job: TrackAuctionViewJob::dispatch($auction->id)
   c. Continue to next middleware
4. If cache key EXISTS: skip tracking, continue to next middleware

Create the job: app/Jobs/TrackAuctionViewJob.php
- Handles string $auctionId in constructor
- In handle(): Auction::where('id', $auctionId)->increment('views_count')
- Queue: 'analytics' (low priority)
- Tries: 3, backoff: [5, 30]

Register the job in app/Providers/AppServiceProvider.php to use the 'analytics' queue.

Also update config/queue.php to add an 'analytics' queue if it doesn't exist (add to the list, don't change the driver).

Output all three files completely.
```

**Acceptance criteria:**
- [ ] Authenticated and guest views tracked separately
- [ ] Same user cannot increment count more than once per hour per auction
- [ ] Job uses queue 'analytics'
- [ ] Middleware calls `$next($request)` in all paths (never blocks the request)

**Test command:** `sail artisan queue:listen --queue=analytics --tries=1 --timeout=5 2>&1 &`

**Commit message:** `feat(middleware): implement auction view tracking with hourly throttle and queued increment`

---

## PHASE 2 — Homepage & Discovery Redesign
> Replaces the generic welcome page and adds a personalised discovery layer.
> **Estimated micro-tasks:** 8
> **Agent parallelism:** 2.1–2.3 can run simultaneously. 2.4 depends on 2.1.

---

### 2.1 — Redesign welcome.blade.php — hero section

**Goal:** Replace the current static hero with a dynamic hero that shows live auction stats pulled from cache.

**Agent context:**
```
You are redesigning `resources/views/welcome.blade.php` in BidFlow (Laravel 11 + Tailwind).

TASK: Redesign ONLY the hero section (the first visible section above the fold). Keep all other sections.

The hero must:
1. Show a full-width gradient background (indigo-900 to purple-900, dark and rich)
2. Left column (text):
   - Animated badge: "● 247 Live Auctions" (number from $liveCount passed from controller)
   - H1: "Bid on things worth having." (large, white, Playfair Display serif font already loaded)
   - Subhead: "Verified sellers. Real-time bidding. Secure escrow." (text-indigo-200)
   - Two CTA buttons: "Browse Live Auctions" (white bg, dark text) and "Become a Seller" (outline white)
   - Trust bar: "100% Secure Escrow · Anti-snipe Protection · Instant Payouts"
3. Right column — a vertically stacked preview of 3 featured auction cards:
   - Each mini-card shows: auction image (60x60 rounded), title (truncated), current price, and a live countdown badge
   - These cards are passed as $featuredAuctions from the controller (collection of up to 3 Auction models)
   - Each links to route('auctions.show', $auction)

The controller update needed (add to the existing closure in routes/web.php for '/'):
  $liveCount = Cache::remember('live_auction_count', 60, fn() => Auction::where('status','active')->count());
  $featuredAuctions = Cache::remember('featured_auctions_hero', 120, fn() => 
    Auction::featured()->with('media')->take(3)->get()
  );
  return view('welcome', compact('liveCount', 'featuredAuctions'));

Output: the complete new hero section HTML (replace from <!-- Hero Section --> to the end of its closing </div>), plus the route update.

Use Tailwind only. No custom CSS. Must be mobile responsive.
```

**Acceptance criteria:**
- [ ] `$liveCount` and `$featuredAuctions` rendered without errors when collections are empty
- [ ] Hero section is responsive (single column on mobile, two-column on lg+)
- [ ] Featured auction cards link correctly
- [ ] No external images used (only `$auction->getCoverImageUrl()`)

**Test command:** `sail artisan route:list | grep "GET /"  && curl -s http://localhost/  | grep -c "Live Auctions"`

**Commit message:** `feat(home): redesign hero section with live stats and featured auction preview`

---

### 2.2 — Homepage: featured auctions carousel section

**Goal:** Add a "Featured Auctions" section below the hero with horizontally scrollable cards.

**Agent context:**
```
TASK: Add a "Featured Auctions" section to resources/views/welcome.blade.php in BidFlow.

Insert this section AFTER the hero section, BEFORE the "How it Works" section.

The section:
- Section heading: "Featured Right Now" with a "View all →" link to route('auctions.index')
- A horizontally scrollable strip of auction cards (scroll-snap on mobile, grid on desktop)
- Each card (from $featuredAuctions already passed to the view — expand to 8 items):
  * Full image at top (aspect-video, object-cover)
  * Auction title (font-semibold, truncate)
  * Category badge (from $auction->primaryCategory->first()?.name)
  * Current price (green, font-bold)
  * Bids count and time remaining
  * "Bid Now" button linking to auctions.show

Update the controller to fetch 8 featured auctions instead of 3.

Mobile: horizontal scroll with scroll-snap-type x mandatory, each card min-w-[280px]
Desktop (lg+): CSS grid 4 columns

Also add an "Ending Soon" section below with the same card format but filtering by:
  Auction::active()->where('end_time', '<=', now()->addHours(6))->orderBy('end_time')->take(8)->get()
Pass as $endingSoonAuctions from the controller (cached for 60 seconds).

Use x-ui-price, x-ui-badge, and x-ui-countdown components created in Phase 1.
```

**Acceptance criteria:**
- [ ] Empty state renders gracefully (no PHP errors if collections are empty)
- [ ] Horizontal scroll works on mobile
- [ ] Desktop shows 4-column grid
- [ ] Phase 1 components used (x-ui-price, x-ui-badge, x-ui-countdown)
- [ ] Controller changes cache both queries

**Test command:** `php artisan view:clear && curl -s http://localhost/ | grep -c "Featured Right Now"`

**Commit message:** `feat(home): add featured auctions and ending-soon sections to homepage`

---

### 2.3 — Redesign auctions/index.blade.php — filter sidebar

**Goal:** Replace the top-of-page filter form with a persistent left sidebar filter panel that updates results without page reload using Alpine.js + fetch.

**Agent context:**
```
You are redesigning the filter system in `resources/views/auctions/index.blade.php` in BidFlow.

CURRENT DESIGN: Filters are a horizontal bar at the top of the page that does a full page reload on submit.

NEW DESIGN:
1. Layout changes to a two-column grid: 
   - Left: w-64 sticky sidebar (top-24, height: calc(100vh - 6rem), overflow-y-auto)
   - Right: flex-1 auction grid

2. Sidebar contains:
   - "Filters" heading with "Clear all" button (links to route('auctions.index'))
   - Active filter chips: for each active filter, show a removable pill
   - Search input (text, name="q")
   - Price range: two number inputs side by side (min_price, max_price)
   - Condition select
   - Sort select
   - An "Apply Filters" button at bottom that submits the form
   - The entire sidebar is a <form method="GET"> so it works without JS
   
3. Progressive enhancement with Alpine.js:
   - x-data="{ loading: false }"
   - On form submit, if JS available: prevent default, set loading=true, update URL with URLSearchParams, fetch the page (same URL with new params), extract the auction grid from the response HTML, replace it in the DOM, set loading=false
   - Show a skeleton loader (3 placeholder cards with animate-pulse) while loading

4. Auction grid stays in its current position in the right column.

5. On mobile (< lg): sidebar becomes a slide-over drawer triggered by a "Filters" button at top.
   Use Alpine.js x-data="{ open: false }" with x-show and x-transition on the drawer.

Output the COMPLETE redesigned auctions/index.blade.php.
The PHP logic (passing $auctions, $conditions, $rootCategories etc.) does not change.
```

**Acceptance criteria:**
- [ ] Form works without JavaScript (full page reload path)
- [ ] With JS, URL updates and grid refreshes without full reload
- [ ] Mobile drawer opens/closes with animation
- [ ] Active filter chips shown for each non-empty filter
- [ ] Loading skeleton shown during fetch
- [ ] Pagination links still work correctly

**Test command:** `php artisan view:clear && grep -c "slide-over\|x-transition\|skeleton" resources/views/auctions/index.blade.php`

**Commit message:** `feat(auctions): redesign filter system with sticky sidebar and progressive enhancement`

---

### 2.4 — Redesign auctions/show.blade.php — layout restructure

**Goal:** Restructure the auction detail page into a two-column layout with sticky bid panel. This is the highest-impact page redesign.

**Agent context:**
```
You are redesigning `resources/views/auctions/show.blade.php` in BidFlow (Laravel 11 + Alpine.js + Tailwind).

CURRENT LAYOUT: Linear, single column. Bid form is in the right column but scrolls away.

NEW LAYOUT — two column grid (lg:grid-cols-[1fr_380px]):
LEFT COLUMN (scrollable content):
  1. Image gallery (keep existing Alpine gallery, add fullscreen button)
  2. Auction info card: title, badges (condition, brand, is_featured), description
  3. Specifications table (brand, SKU, serial, dynamic attributes) 
  4. Tags section
  5. Bid history (keep existing, add "Show all X bids" expandable button after 10)
  6. Seller message form (keep existing)

RIGHT COLUMN (sticky top-24):
  1. Price card (use x-ui-price with animate=true)
  2. Countdown timer (use x-ui-countdown)
  3. Reserve status (use x-ui-badge)
  4. Bid form — RESTRUCTURE:
     - Quick-bid buttons: [Min (+$X)] [+5%] [+10%] [Custom]
     - Clicking a quick button fills the input automatically
     - Large input field
     - "Place Bid" button (use x-ui-button variant="primary" size="lg" full-width)
  5. Auto-bid card (keep existing, style with x-ui-card)
  6. Seller card: avatar, name, rating stars placeholder (5 stars, gray), link to storefront, "Contact Seller" button

Keep ALL existing JavaScript functionality (countdown, bid form fetch, watch toggle, auto-bid, WebSocket).
Replace the existing big <script type="module"> with BidEventBus.subscribe() call.
Use x-ui-countdown instead of the custom countdown script.

Output the COMPLETE redesigned auctions/show.blade.php.
```

**Acceptance criteria:**
- [ ] Bid panel is `sticky top-24` on desktop
- [ ] Single column on mobile, two column on lg+
- [ ] Quick-bid buttons calculate correctly (min+increment, +5%, +10%)
- [ ] All existing JS functionality preserved
- [ ] BidEventBus.subscribe() used instead of inline Echo
- [ ] x-ui-countdown, x-ui-price, x-ui-badge, x-ui-card used

**Test command:** `sail artisan view:clear && grep -c "BidEventBus\|x-ui-" resources/views/auctions/show.blade.php`

**Commit message:** `feat(auctions): full layout redesign of auction show page with sticky bid panel and quick-bid buttons`

---

### 2.5 — Redesign user dashboard (dashboard.blade.php)

**Goal:** Replace the "You're logged in!" placeholder with a real activity hub showing the user's auction activity at a glance.

**Agent context:**
```
TASK: Redesign `resources/views/dashboard.blade.php` in BidFlow. This currently shows only "You're logged in!".

The view already receives data from `app/Http/Controllers/User/DashboardController.php`. Use ALL the variables it provides: $activeBids, $wonItems, $watchedItems, $activeBidCount, $wonUnpaidCount, $user.

NEW LAYOUT:

1. Greeting bar: "Good morning, {name}" with current date, and wallet balance pill on the right.

2. Stats row (4 cards using x-ui-card):
   - Active bids ($activeBidCount) 
   - Winning ($activeBids->where('is_winning', true)->count())
   - Won unpaid ($wonUnpaidCount) — red if > 0
   - Watchlist count (link to user.watchlist)

3. "Your Active Bids" section:
   - Table with columns: Item, Your Bid, Current Price, Status, Time Left, Action
   - Status badge: Winning (green) or Outbid (red, animate-pulse)
   - Outbid status listens to 'outbid-notification' CustomEvent to update reactively
   - "Raise bid" button for Outbid rows (links to auctions.show)
   - Empty state: "No active bids. Browse auctions →"

4. "Won — Awaiting Payment" section (only show if $wonItems not empty):
   - Grid of won auction cards with "Pay Now" CTA
   - Show winning amount prominently

5. "Ending Soon on Your Watchlist" section:
   - Compact list: image, title, current price, time left, "Bid Now" link
   - Show max 5 items, "View watchlist →" link

Use x-ui-card, x-ui-badge, x-ui-price, x-ui-countdown, x-ui-button throughout.
Output the COMPLETE redesigned dashboard.blade.php.
```

**Acceptance criteria:**
- [ ] No "You're logged in!" text remaining
- [ ] All 5 sections render correctly when collections are empty
- [ ] Outbid status updates from CustomEvent without page reload
- [ ] x-ui components used consistently
- [ ] Wallet balance shown in stats bar

**Test command:** `php artisan view:clear && grep "You're logged in" resources/views/dashboard.blade.php && echo "FAIL: old content remains" || echo "PASS"`

**Commit message:** `feat(dashboard): replace placeholder with full activity hub showing bids, won items, and watchlist`

---

### 2.6 — Create AuctionCardComponent — reusable auction card

**Goal:** Extract the auction card HTML into a reusable Blade component used on homepage, index, category pages, and search results. Single source of truth.

**Agent context:**
```
TASK: Create `resources/views/components/auction/card.blade.php` in BidFlow.

This replaces the duplicated auction card HTML that currently exists in:
- resources/views/auctions/index.blade.php
- resources/views/welcome.blade.php  
- resources/views/categories/show.blade.php
- resources/views/user/watchlist.blade.php
- resources/views/user/dashboard.blade.php

Props:
- $auction: Auction model (required)
- $size: 'sm' | 'md' | 'lg' (default: 'md')
- $showSeller: bool (default: false)
- $showCategory: bool (default: true)

The card renders:
1. Image area (aspect-video): uses $auction->getCoverImageUrl('gallery') with gray placeholder. In top-right corner overlay: "★ Featured" badge if $auction->is_featured.
2. Body:
   - Title (font-bold, line-clamp-2 for md/lg, line-clamp-1 for sm)
   - Badges row: condition badge (x-ui-badge, blue), brand name (gray text) if $auction->brand
   - Tags (first 3, colored pills) if md or lg size
   - Price and bids: x-ui-price size=sm, bids count text
   - Reserve indicator: x-ui-badge if $auction->hasReserve()
3. Footer:
   - Left: x-ui-countdown :ends-at="$auction->end_time->toIso8601String()" size="sm"
   - Right: "Bid Now" x-ui-button variant="primary" size="sm" :href="route('auctions.show', $auction)"

After creating the component, update auctions/index.blade.php and welcome.blade.php to use:
  <x-auction.card :auction="$auction" />

Output: the component file, and the @foreach loop replacement in both updated files.
```

**Acceptance criteria:**
- [ ] Component renders without errors for auctions with no images, no brand, no tags
- [ ] All three size variants produce different visual density
- [ ] `getCoverImageUrl()` null safety handled
- [ ] Updated files use the component (no duplicated card HTML)

**Test command:** `grep -c "getCoverImageUrl" resources/views/auctions/index.blade.php`

**Commit message:** `refactor(components): extract auction card into reusable x-auction.card component`

---

### 2.7 — Keyword alerts: model, migration, and controller

**Goal:** Let users subscribe to keyword searches and receive notifications when matching auctions are listed.

**Agent context:**
```
TASK: Implement the keyword alert system for BidFlow (Laravel 11).

1. Migration: database/migrations/YYYY_MM_DD_create_keyword_alerts_table.php
   Table: keyword_alerts
   Columns:
   - id, user_id (FK cascade), keyword (varchar 100), is_active (bool DEFAULT true)
   - last_notified_at (timestamp nullable)
   - notify_email (bool DEFAULT true), notify_database (bool DEFAULT true)
   - created_at, updated_at
   Add index: (user_id, keyword) — users can't have duplicate keywords

2. Model: app/Models/KeywordAlert.php
   - belongsTo User
   - scopeActive(): is_active = true
   - static method: matchingAuction(Auction $auction): queries where auction title ILIKE '%keyword%'

3. Controller: app/Http/Controllers/User/KeywordAlertController.php
   - index(): return view with user's alerts paginated
   - store(Request $request): validate keyword (required, max:100, unique per user), create alert, redirect back with success
   - destroy(KeywordAlert $alert): authorize user owns it, delete, redirect back
   - toggle(KeywordAlert $alert): flip is_active, return JSON {active: bool}

4. Routes in routes/web.php (inside auth middleware group):
   GET  /dashboard/keyword-alerts          → index   → user.keyword-alerts
   POST /dashboard/keyword-alerts          → store   → user.keyword-alerts.store
   DELETE /dashboard/keyword-alerts/{alert} → destroy → user.keyword-alerts.destroy
   PATCH /dashboard/keyword-alerts/{alert}/toggle → toggle → user.keyword-alerts.toggle

5. Simple view: resources/views/user/keyword-alerts.blade.php
   - Form to add new alert (keyword input + submit)
   - List of existing alerts with toggle switch and delete button
   - Empty state: "You have no keyword alerts set up."
   - Use x-ui-button, x-ui-card, x-ui-badge

Output all 5 files completely.
```

**Acceptance criteria:**
- [ ] `sail artisan migrate --pretend` runs clean
- [ ] Unique constraint prevents duplicate keywords per user
- [ ] `toggle` route returns JSON (not redirect)
- [ ] `destroy` returns 403 if wrong user tries to delete
- [ ] View renders empty state correctly

**Test command:** `sail artisan route:list | grep "keyword-alerts"`

**Commit message:** `feat(alerts): add keyword alert system with model, controller, routes, and view`

---

### 2.8 — Job: send keyword alert notifications when auction created

**Goal:** When a new auction is published, check all keyword alerts and notify matching users.

**Agent context:**
```
TASK: Create a job that sends keyword alert notifications when an auction is published in BidFlow.

1. Create app/Jobs/ProcessKeywordAlerts.php:
   Constructor: public function __construct(public readonly int $auctionId) {}
   Queue: 'notifications', tries: 3, backoff: [10, 60]
   
   handle() method:
   - Load the auction (return early if not found or not active)
   - Load all active keyword alerts: KeywordAlert::active()->with('user')->get()
   - For each alert, check if $auction->title ILIKE '%{$alert->keyword}%' (case-insensitive)
   - If match AND alert->user_id !== $auction->user_id (don't notify seller of their own auction):
     a. Create a database notification (if alert->notify_database)
     b. Send an email notification (if alert->notify_email) using Mail::queue
     c. Update alert->last_notified_at = now()
   - Use chunk(100) to avoid memory issues with large alert sets

2. Create app/Notifications/KeywordAlertNotification.php:
   - Constructor: public function __construct(public readonly Auction $auction, public readonly string $keyword) {}
   - via(): return array with 'database' always, add 'mail' based on user preference
   - toMail(): MailMessage with subject "New auction matching '{$keyword}'", line about auction title, action button "View Auction" linking to route('auctions.show', $auction->id)
   - toArray(): return ['auction_id' => ..., 'auction_title' => ..., 'keyword' => ..., 'message' => ...]

3. Dispatch the job from Auction model using a 'published' event:
   In app/Models/Auction.php, add a static boot() method (or update existing):
   static::updated(function (Auction $auction) {
     if ($auction->isDirty('status') && $auction->status === 'active') {
       ProcessKeywordAlerts::dispatch($auction->id)->delay(now()->addSeconds(5));
     }
   });

Output all modified and created files.
```

**Acceptance criteria:**
- [ ] Job dispatched only when status changes TO 'active' (not on every update)
- [ ] Seller not notified about their own auction
- [ ] `chunk(100)` used to avoid memory issues
- [ ] Notification has correct `toArray()` shape for the notification bell component
- [ ] 5-second delay prevents job running before DB transaction commits

**Test command:** `sail artisan queue:listen --queue=notifications --tries=1 --timeout=10 2>&1 &`

**Commit message:** `feat(notifications): dispatch keyword alert notifications when auction is published`

---

## PHASE 3 — Auction Detail Page Features
> New features on the auction show page: quick bid, price history, Q&A, two-way ratings.
> **Estimated micro-tasks:** 9

---

### 3.1 — Quick-bid increment buttons

**Goal:** Add three preset bid buttons above the amount input: [Min], [+5%], [+10%]. Clicking pre-fills the input. Pure Alpine.js, no backend changes.

**Agent context:**
```
TASK: Add quick-bid preset buttons to the bid form in resources/views/auctions/show.blade.php in BidFlow.

The existing bid form has:
- An input#bid-amount (type=number, min=$auction->minimumNextBid(), value=minimumNextBid)
- A "Place Bid" button

ADD ABOVE the input:
  <div class="grid grid-cols-3 gap-2 mb-3">
    Three buttons with these labels and values:
    1. "Min ($X.XX)" — value = minimumNextBid (formatted)
    2. "+5% ($X.XX)" — value = currentPrice * 1.05, rounded to 2 decimal places
    3. "+10% ($X.XX)" — value = currentPrice * 1.10, rounded to 2 decimal places

These buttons:
- Are NOT submit buttons (type="button")
- On click: set the #bid-amount input's value to their respective calculated value
- Visual style: secondary variant, small size — bg-gray-50 border border-gray-200 text-sm font-medium rounded-lg hover:bg-gray-100
- The "Min" button is slightly highlighted (border-indigo-200 bg-indigo-50) as it is the recommended choice

The values shown in the button labels must UPDATE when a new bid comes in via WebSocket:
- Listen to window 'bid:placed' CustomEvent
- Update currentPrice with event.detail.amount
- Recalculate all three button values and update their labels

Wrap the three buttons in an Alpine x-data component:
  x-data="quickBid({ minBid: {{ $auction->minimumNextBid() }}, currentPrice: {{ $auction->current_price }}, increment: {{ $auction->min_bid_increment }} })"

Define window.quickBid as a factory function in resources/js/bid-ui.js:
  window.quickBid = function({ minBid, currentPrice, increment }) {
    return {
      min: minBid,
      current: currentPrice,
      inc: increment,
      init() {
        window.addEventListener('bid:placed', (e) => {
          this.current = parseFloat(e.detail.amount);
          this.min = this.current + this.inc;
        });
      },
      get minLabel() { return `Min ($${this.min.toFixed(2)})`; },
      get plus5Label() { return `+5% ($${(this.current * 1.05).toFixed(2)})`; },
      get plus10Label() { return `+10% ($${(this.current * 1.10).toFixed(2)})`; },
      setMin()   { document.getElementById('bid-amount').value = this.min.toFixed(2); },
      setPlus5() { document.getElementById('bid-amount').value = (this.current * 1.05).toFixed(2); },
      setPlus10(){ document.getElementById('bid-amount').value = (this.current * 1.10).toFixed(2); },
    }
  }

Output: the modified bid form section of auctions/show.blade.php and the addition to bid-ui.js.
```

**Acceptance criteria:**
- [ ] Three buttons render with correct initial values
- [ ] Clicking each button fills the input with correct value
- [ ] Button labels update when 'bid:placed' event fires
- [ ] Buttons are type="button" (do not submit the form)
- [ ] Min button visually distinguished from the others

**Test command:** `grep -c "quickBid\|setMin\|setPlus" resources/js/bid-ui.js`

**Commit message:** `feat(bidding): add quick-bid preset buttons with live price updates`

---

### 3.2 — Auction Q&A section

**Goal:** Add a public Q&A section to the auction detail page where buyers can ask questions and sellers answer publicly.

**Agent context:**
```
TASK: Implement the auction Q&A (Questions & Answers) feature in BidFlow (Laravel 11).

1. Migration: database/migrations/YYYY_MM_DD_create_auction_questions_table.php
   Table: auction_questions
   Columns:
   - id, auction_id (FK cascade), user_id (FK cascade — the asker)
   - question (text, NOT NULL)
   - answer (text, nullable)
   - answered_at (timestamp nullable)
   - is_visible (bool DEFAULT true — admin can hide spam)
   - created_at, updated_at

2. Model: app/Models/AuctionQuestion.php
   - belongsTo Auction, belongsTo User (as asker), belongsTo User as 'answerer' (answered_by_id)
   - scopeVisible(): is_visible = true
   - isAnswered(): returns bool

3. Controller: app/Http/Controllers/AuctionQuestionController.php
   - store(Request $request, Auction $auction):
     Validate: question required|string|max:500
     Must be authenticated, must not be the seller of this auction
     Create question linked to auction and auth user
     Return redirect back with success flash
   - answer(Request $request, AuctionQuestion $question):
     Authorize: auth user must be the auction's seller
     Validate: answer required|string|max:1000
     Update question: answer, answered_at=now(), answered_by_id=auth()->id()
     Return redirect back
   - destroy(AuctionQuestion $question):
     Authorize: auth user is asker OR auction seller
     Delete
     Return redirect back

4. Routes (inside auth middleware):
   POST /auctions/{auction}/questions → AuctionQuestionController@store → auctions.questions.store
   PATCH /questions/{question}/answer → AuctionQuestionController@answer → questions.answer
   DELETE /questions/{question} → AuctionQuestionController@destroy → questions.destroy

5. Blade section to add to auctions/show.blade.php:
   Below the "Recent Bids" section, add:
   - "Questions & Answers" heading with count badge
   - Loop of visible questions: question text, asker name, date, answer (if present, indented with border-left)
   - If no answer and auth user is seller: inline answer textarea + submit button
   - At bottom: "Ask a Question" form (textarea + submit), shown only to authenticated non-sellers
   - Use x-ui-card for each Q block

Output all files completely.
```

**Acceptance criteria:**
- [ ] Seller cannot ask questions on their own auction
- [ ] Only seller can answer questions
- [ ] Questions without answers show no answer section
- [ ] Visible questions show in chronological order
- [ ] Unauthenticated users see questions but not the ask form

**Test command:** `sail artisan route:list | grep "questions"`

**Commit message:** `feat(qa): add public Q&A system to auction detail pages`

---

### 3.3 — Two-way rating system

**Goal:** After an auction completes and payment is captured, both buyer and seller can rate each other. Ratings appear on seller storefronts and user profiles.

**Agent context:**
```
TASK: Implement the two-way rating system for BidFlow (Laravel 11).

1. Migration: database/migrations/YYYY_MM_DD_create_auction_ratings_table.php
   Table: auction_ratings
   Columns:
   - id
   - auction_id (FK cascade)
   - rater_id (FK users, cascade) — who wrote the rating
   - ratee_id (FK users, cascade) — who is being rated
   - role: enum('buyer', 'seller') — the role of the RATEE
   - score: tinyint (1-5)
   - comment: text nullable (max 500 chars)
   - created_at, updated_at
   Unique constraint: (auction_id, rater_id) — one rating per auction per person

2. Model: app/Models/AuctionRating.php
   - belongs to Auction, User (rater), User (ratee)
   - scopeForSeller($userId): ratee_id=$userId AND role='seller'
   - scopeForBuyer($userId): ratee_id=$userId AND role='buyer'
   - static averageForUser(int $userId): float — average score across all ratings as ratee

3. Controller: app/Http/Controllers/AuctionRatingController.php
   - create(Auction $auction):
     Must be auth, auction must be completed AND payment_status = 'captured'
     Auth user must be buyer OR seller of this auction
     Check if already rated (return with error if so)
     Determine ratee: if rater=buyer, ratee=seller and vice versa
     Return view with $auction, $ratee
   - store(Request $request, Auction $auction):
     Same authorization checks as create()
     Validate: score integer 1-5 required, comment nullable string max:500
     Create rating
     Redirect to auction with success message

4. Routes (auth middleware):
   GET  /auctions/{auction}/rate  → create  → auctions.rate
   POST /auctions/{auction}/rate  → store   → auctions.rate.store

5. Update User model:
   - hasMany AuctionRating (as ratee_id), as 'ratingsReceived'
   - Add accessor: getAverageRatingAttribute() — null if no ratings
   - Add accessor: getRatingCountAttribute()

6. Update seller storefront view (resources/views/seller/storefront/show.blade.php):
   Add average rating display (star icons, score, count) in the seller info card.
   Pass $averageRating and $ratingCount from the controller.

7. Simple rating form view: resources/views/auctions/rate.blade.php
   - Star selector (5 stars, Alpine.js hover/click to select)
   - Optional comment textarea
   - Submit button

Output all files completely.
```

**Acceptance criteria:**
- [ ] Unique constraint prevents double-rating
- [ ] Only auction participants can rate
- [ ] Only completed auctions with captured payments can be rated
- [ ] Star UI uses Alpine.js (hover changes preview, click sets value)
- [ ] Average rating shows on storefront

**Test command:** `php artisan route:list | grep "rate" && php artisan migrate --pretend 2>&1 | tail -5`

**Commit message:** `feat(ratings): add two-way buyer/seller rating system after auction completion`

---

### 3.4 — Price history chart on auction show page

**Goal:** Show a small sparkline or bar chart of the bid history over time so buyers can see price velocity.

**Agent context:**
```
TASK: Add a bid price history chart to resources/views/auctions/show.blade.php in BidFlow.

The chart shows bid amounts over time for the current auction.

Backend:
In app/Http/Controllers/AuctionController.php show() method, add to the data passed to the view:
  $bidChartData = $auction->bids()
    ->orderBy('created_at')
    ->get(['amount', 'created_at'])
    ->map(fn($b) => [
      'x' => $b->created_at->toIso8601String(),
      'y' => (float) $b->amount,
    ])
    ->values()
    ->toJson();

Pass $bidChartData to the view.

Frontend — add inside the auction show page, in a x-ui-card below the specs section:
  <x-ui-card>
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-medium text-gray-900 dark:text-gray-100">Price History</h3>
      <span class="text-sm text-gray-500">{{ $auction->bids->count() }} bids</span>
    </div>
    @if($auction->bids->count() > 0)
      <canvas id="price-chart" height="120"></canvas>
    @else
      <p class="text-sm text-gray-400 text-center py-4">No bids yet</p>
    @endif
  </x-ui-card>

Use Chart.js (load from CDN: https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js):
- Line chart, no point dots for sm screens
- X axis: time, formatted as "HH:mm" using built-in Chart.js time adapter
- Y axis: dollar amounts, no decimals if whole number
- Color: indigo-500 line, indigo-100 fill (area chart)
- No legend, no title (the card heading serves that purpose)
- Responsive: true, maintainAspectRatio: false
- Append new bids to the chart when 'bid:placed' CustomEvent fires:
  window.addEventListener('bid:placed', (e) => {
    chart.data.datasets[0].data.push({ x: new Date().toISOString(), y: parseFloat(e.detail.amount) });
    chart.update('active');
  });

Load Chart.js in @push('scripts') at the bottom of the view.
Also load the Chart.js date-fns adapter: https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js

Output: the chart section HTML, the script block, and the controller change.
```

**Acceptance criteria:**
- [ ] Chart renders without error when there are 0 bids
- [ ] Chart renders without error when there are 1+ bids
- [ ] New bids appended to chart via CustomEvent
- [ ] Chart.js loaded from CDN in @push('scripts')
- [ ] Canvas height fixed at 120px (not growing with data)

**Test command:** `grep -c "cdn.jsdelivr.net/npm/chart.js" resources/views/auctions/show.blade.php`

**Commit message:** `feat(auctions): add live bid price history chart to auction detail page`

---

### 3.5 — Auction report/flag for buyers

**Goal:** Move the reporting UI from admin-only to buyer-facing. Any authenticated user who is not the seller can report an auction.

**Agent context:**
```
TASK: Add a "Report this auction" button and modal to resources/views/auctions/show.blade.php in BidFlow.

The existing reports system already has:
- app/Models/AuctionReport (or similar) — check the existing model and migration
- admin/reports views and controller

What's MISSING: the buyer-facing report form.

ADD to the auction show page (near the seller contact section, below the description):
A "Report this listing" text link (small, gray, only shown to auth users who are NOT the seller).

Clicking opens an Alpine.js modal (x-data, no fixed positioning — use a faux viewport wrapper div as per design rules):
  Modal content:
  - "Report this listing" heading
  - Reason select: ['Item description inaccurate', 'Counterfeit or fake item', 'Prohibited item', 'Seller fraud', 'Other']
  - Description textarea (optional, max 500 chars)
  - "Submit Report" button (x-ui-button danger)
  - "Cancel" button

Form POSTs to a new route:
  POST /auctions/{auction}/report → AuctionReportController@store → auctions.report

Create app/Http/Controllers/AuctionReportController.php:
  store(Request $request, Auction $auction):
  - Auth required
  - Reporter must not be the auction's seller
  - Validate: reason required|in:[options], description nullable|max:500
  - Check: user hasn't already reported this auction (return 422 with error if so)
  - Create report: auction_id, reporter_id=auth()->id(), reason, description, status='pending'
  - Return JSON: { success: true, message: "Report submitted. Our team will review it." }
  
The form submits via fetch() (not full page reload).
On success: close modal, show window.toast.success(data.message).
On error: show window.toast.error(data.message).

Add the route in routes/web.php inside the auth middleware group.
```

**Acceptance criteria:**
- [ ] Report button not visible to the auction's seller
- [ ] Report button not visible to unauthenticated users  
- [ ] Duplicate report returns 422
- [ ] Modal opens and closes without page reload
- [ ] Success path closes modal AND shows toast

**Test command:** `php artisan route:list | grep "report"`

**Commit message:** `feat(auctions): add buyer-facing report listing form with modal and duplicate protection`

---

## PHASE 4 — Seller Portal Redesign
> Improves the seller experience with better analytics, scheduling, and listing tools.
> **Estimated micro-tasks:** 7

---

### 4.1 — Seller dashboard redesign

**Goal:** Upgrade the seller dashboard with real-time metrics, a revenue mini-chart, and a listing health score for each active auction.

**Agent context:**
```
TASK: Redesign `resources/views/seller/dashboard.blade.php` in BidFlow.

The existing seller dashboard shows basic stats. Enhance it significantly.

NEW SECTIONS:

1. Stats bar (row of 4 metric cards using x-ui-card):
   - Gross Revenue (this month): from existing $stats['total_revenue']
   - Active listings: $stats['active_auctions'] — link to seller.auctions.index?status=active
   - Bids received (today): new stat — pass from controller
   - Conversion rate: completed / total auctions as %, pass from controller

2. Live listings table (replace the current simple table):
   Columns: Thumbnail | Title | Current Price | Bids | Ends | Health | Actions
   Health column: a simple 3-state indicator:
   - "Hot" (green dot) if bids_count > 5
   - "Active" (blue dot) if bids_count >= 1
   - "No bids" (red dot) if bids_count = 0
   Actions: "View", "Edit", "Insights" links

3. Revenue chart (last 30 days):
   Fetch from a new endpoint: GET /seller/revenue/chart-data (returns JSON array of {date, revenue})
   Render as a Chart.js line chart (same pattern as Phase 3.4)
   Pass data via a blade variable $revenueChartData (JSON)

4. Recent messages strip (3 most recent unread conversations):
   Link to seller.messages.show for each
   Show buyer name, auction title truncated, message preview

Controller changes needed (SellerDashboardController@index):
  Add to $stats:
  - 'bids_today' => auth()->user()->auctions()->withCount('bids')->whereHas('bids', fn($q) => $q->whereDate('created_at', today()))->sum('bids_count')
  - 'conversion_rate' => ... calculate as percentage
  Add $recentMessages from Conversation::where('seller_id', auth()->id())->with(['buyer','auction','messages' => fn($q) => $q->latest()->limit(1)])->latest('last_message_at')->take(3)->get()
  Add $revenueChartData as JSON

Output: complete redesigned dashboard.blade.php and the controller additions.
```

**Acceptance criteria:**
- [ ] All 4 stats cards render with correct values
- [ ] Health column shows correct indicator (hot/active/no bids)
- [ ] Revenue chart renders from blade variable
- [ ] Recent messages section shows max 3 items
- [ ] Empty states for each section when data is absent

**Test command:** `php artisan view:clear && php artisan route:list | grep "seller.dashboard"`

**Commit message:** `feat(seller): redesign seller dashboard with health scores, revenue chart, and messages strip`

---

### 4.2 — Auction scheduling calendar view

**Goal:** Add a calendar/timeline view of auction start/end times in the seller auction list.

**Agent context:**
```
TASK: Add an "Auction Schedule" calendar view to the seller auctions section in BidFlow.

Add a new route and view:
Route: GET /seller/auctions/schedule → SellerScheduleController@index → seller.auctions.schedule

Controller: app/Http/Controllers/Seller/ScheduleController.php
  index():
  - Fetch all seller auctions from 7 days ago to 30 days in the future
  - Group by date (using Carbon): $auctionsByDate = auctions grouped by date string 'Y-m-d'
  - Pass also: $today = today()->toDateString(), $weeks = 5 (display 5 weeks)
  - Return view

View: resources/views/seller/auctions/schedule.blade.php
  - Show a calendar grid (7 columns for days, multiple rows for weeks)
  - For each day cell: show any auction ending or starting on that day as a colored pill
    * Starting: blue pill with auction title truncated to 20 chars
    * Ending: orange pill
    * Both: show both pills
  - Clicking a pill links to seller.auctions.edit for that auction
  - Current day highlighted with bg-indigo-50 border
  - Days with no auctions: empty cell
  - Past days: slightly muted (opacity-60)
  - Add a "Create New Auction" button linking to seller.auctions.create
  - Add a toggle to switch between Calendar view and List view (link back to seller.auctions.index)

Mobile: fall back to a simple list grouped by date (hide the grid, show the list)

Add link to this view from the seller dashboard and from seller.auctions.index.

Output: ScheduleController.php and schedule.blade.php completely.
```

**Acceptance criteria:**
- [ ] Calendar shows 5 weeks from today
- [ ] Auctions with both start and end on same day show both pills
- [ ] Past days are visually distinct
- [ ] Mobile fallback renders correctly (Tailwind responsive classes)
- [ ] Link from seller.auctions.index exists

**Test command:** `php artisan route:list | grep "schedule"`

**Commit message:** `feat(seller): add auction scheduling calendar view with start/end timeline`

---

### 4.3 — Bulk auction creation via CSV import

**Goal:** Let sellers upload a CSV file to create multiple auctions at once (as drafts).

**Agent context:**
```
TASK: Implement CSV auction import for sellers in BidFlow (Laravel 11).

1. Route: GET  /seller/auctions/import → ImportController@create → seller.auctions.import
         POST /seller/auctions/import → ImportController@store  → seller.auctions.import.store

2. Controller: app/Http/Controllers/Seller/ImportController.php
   create(): return view with CSV template download link
   store(Request $request):
   - Validate: file required, mimes:csv,txt, max:2048
   - Parse CSV using str_getcsv or League\Csv if available
   - Expected columns: title, description, starting_price, reserve_price, min_bid_increment, start_time, end_time, condition, tags (comma-separated)
   - For each valid row: create Auction in 'draft' status linked to auth seller
   - Collect errors by row number
   - Return back with: $created (count), $errors (array of [row, message])

3. CSV template download route:
   GET /seller/auctions/import/template → returns a CSV file download with headers and one example row

4. View: resources/views/seller/auctions/import.blade.php
   - Instruction text: "Upload a CSV file to create multiple auctions as drafts."
   - CSV template download button
   - File upload form
   - On POST, show results: "X auctions created" in green
   - Show error table if any rows failed: Row | Error message
   - Link to seller.auctions.index to view created drafts

5. Add to existing create.blade.php: a small "Import from CSV →" link at the top right of the form.

6. Required CSV columns (validate headers, return error if missing):
   title, description, starting_price, end_time
   Optional: reserve_price, min_bid_increment (default from config), start_time, condition, tags

Output: ImportController.php, import.blade.php, template CSV file (as a PHP string in the controller).
```

**Acceptance criteria:**
- [ ] Missing required columns returns a descriptive error (not a 500)
- [ ] Invalid rows (bad dates, negative prices) are skipped with row-level error message
- [ ] Valid rows in same file are still created despite some errors
- [ ] All created auctions are status='draft'
- [ ] Template CSV download has correct headers and example row

**Test command:** `php artisan route:list | grep "import"`

**Commit message:** `feat(seller): add CSV bulk import for creating multiple auction drafts`

---

### 4.4 — Bid funnel analytics per auction

**Goal:** Add a conversion funnel view to auction insights: views → watchers → bidders → winner.

**Agent context:**
```
TASK: Enhance `app/Http/Controllers/Seller/InsightController.php` and `resources/views/seller/insights/show.blade.php` in BidFlow.

The existing insights show basic metrics. ADD a visual funnel.

Controller changes (auctionInsights method):
Add to the $insights array:
  'funnel' => [
    'views'    => $auction->views_count,
    'watchers' => $auction->watchers()->count(),
    'bidders'  => $auction->bids()->distinct('user_id')->count('user_id'),
    'winner'   => $auction->winner_id ? 1 : 0,
  ],
  'funnel_rates' => [
    'view_to_watch' => $auction->views_count > 0 ? round($auction->watchers()->count() / $auction->views_count * 100, 1) : 0,
    'watch_to_bid'  => $auction->watchers()->count() > 0 ? round($auction->bids()->distinct('user_id')->count('user_id') / $auction->watchers()->count() * 100, 1) : 0,
    'bid_to_win'    => $auction->bids()->distinct('user_id')->count('user_id') > 0 ? ($auction->winner_id ? 100 : 0) : 0,
  ]

View changes (seller/insights/show.blade.php):
Replace the basic grid with:

1. Funnel visualization (pure CSS + Tailwind, no JS library needed):
   Four trapezoid-shaped steps, each narrower than the last.
   Use CSS clip-path trick:
   Each step is a div with clip-path: polygon(10% 0%, 90% 0%, 100% 100%, 0% 100%)
   Colors: views=blue, watchers=indigo, bidders=purple, winner=green
   Labels inside each: stage name + count + conversion rate from previous stage

2. Below funnel: conversion rate insights text:
   "X% of viewers watched this auction" (view_to_watch)
   "X% of watchers placed a bid" (watch_to_bid)
   If bid_to_win is 0: "No winner yet" else "Auction sold"

3. Keep existing basic metrics grid above the funnel.

Also add a link from seller.auctions.index: "Insights" next to each active auction's "Edit" link.

Output: the updated controller method and the complete redesigned insights/show.blade.php.
```

**Acceptance criteria:**
- [ ] Funnel renders when all counts are 0
- [ ] Conversion rates show "0%" not NaN or division by zero errors
- [ ] Funnel narrows visually from views → winner (CSS clip-path correct)
- [ ] Link from seller auction list to insights works
- [ ] Handles case where `views_count` column might not exist yet (use `$auction->views_count ?? 0`)

**Test command:** `php artisan view:clear && grep -c "clip-path\|funnel" resources/views/seller/insights/show.blade.php`

**Commit message:** `feat(seller): add visual conversion funnel to auction insights page`

---

## PHASE 5 — Admin Panel Enhancements
> **Estimated micro-tasks:** 6

---

### 5.1 — Admin dashboard: live metrics with auto-refresh and sparklines

**Goal:** Upgrade the admin dashboard live metrics panel from plain numbers to sparkline charts with 10-second auto-refresh.

**Agent context:**
```
TASK: Upgrade the admin dashboard live metrics in resources/views/admin/dashboard.blade.php in BidFlow.

CURRENT: 4 plain number stats that auto-refresh via setInterval every 10 seconds.

UPGRADE:
1. Each metric card now shows:
   - Current value (large, bold)
   - A mini sparkline (last 12 data points, using Chart.js with height=40px)
   - Trend indicator: ↑ in green if last value > previous, ↓ in red if decreasing, → if same

2. Store the last 12 values for each metric in a JS array (in-memory, resets on page load):
   let history = { bids_per_min: [], bids_per_5min: [], active_bidders: [], ending_soon: [] };

3. On each refresh (every 10s), push new value to each array (max 12 items, shift if full).
   Re-render sparklines using Chart.js update().

4. Sparkline config: no axes, no labels, no legend. Just the line. Type: 'line'. Tension: 0.4. Point radius: 0.

5. Metric cards layout: 2x2 grid on mobile, 4 columns on desktop.

6. Add a "Fraud Alerts" section below the metrics:
   Fetch from a new admin API endpoint: GET /admin/metrics/fraud-alerts (returns JSON)
   Create the route and method in DashboardController:
   fraudAlerts(): return JSON array of recent suspicious_activity from the last 2 hours
   Pull from: AuditLog or a direct Auction query for auctions flagged suspicious
   Return max 10 records: [{ auction_id, auction_title, severity, detail, detected_at }]
   Display as a simple table with severity badge (x-ui-badge colors: critical=red, high=amber, warning=blue)

Output: the complete updated admin/dashboard.blade.php and the DashboardController addition.
```

**Acceptance criteria:**
- [ ] Sparklines initialize with empty arrays (no Chart.js errors)
- [ ] History arrays cap at 12 items
- [ ] Trend indicators (↑ ↓ →) correct
- [ ] Fraud alerts section renders empty state "No recent alerts"
- [ ] 10-second interval not duplicated (clearInterval before setInterval)

**Test command:** `grep -c "setInterval\|clearInterval" resources/views/admin/dashboard.blade.php`

**Commit message:** `feat(admin): upgrade dashboard metrics to sparkline charts with fraud alert feed`

---

### 5.2 — Admin: feature auction management UI

**Goal:** Allow admins to mark auctions as featured, set featured duration, and reorder featured auctions from the admin auction list.

**Agent context:**
```
TASK: Add "Feature Auction" functionality to the admin auction management in BidFlow.

1. Update AuctionManagementController:
   Add method feature(Request $request, Auction $auction):
     Validate: duration_hours integer between 1 and 720 (30 days), position nullable integer 1-20
     Update auction: is_featured=true, featured_until=now()->addHours($duration), featured_position=$position
     Log to AuditLog: action='auction.featured', target=auction
     Return JSON: { success: true, message: "Auction featured until {date}" }
   
   Add method unfeature(Auction $auction):
     Update: is_featured=false, featured_until=null, featured_position=null
     Return JSON: { success: true }

2. Routes (inside admin middleware group):
   POST  /admin/auctions/{auction}/feature   → feature   → admin.auctions.feature
   DELETE /admin/auctions/{auction}/feature  → unfeature → admin.auctions.unfeature

3. Update admin/auctions/index.blade.php:
   Add to each auction row in the Actions column:
   - If NOT featured: a "Feature" button that opens an inline form (Alpine x-show) with:
     * Duration select: 24h, 48h, 72h, 1 week, 30 days
     * Position input (number, optional)
     * Confirm button that POSTs via fetch()
   - If IS featured: a "★ Featured" badge + "Remove" link that sends DELETE via fetch()
   - On success: update the row in place, show toast

4. Add to admin/auctions/show.blade.php:
   In the Auction Details section, show feature status:
   If featured: "Featured until {date}" with a "Remove feature" button
   If not featured: "Feature this auction" button with the same inline form

Output: controller additions, route additions, and both view modifications.
```

**Acceptance criteria:**
- [ ] Feature and unfeature work via AJAX (no page reload)
- [ ] Audit log entry created on feature action
- [ ] `featured_until` sets to correct future timestamp
- [ ] Removing feature clears all three fields (is_featured, featured_until, featured_position)
- [ ] Both admin views updated

**Test command:** `php artisan route:list | grep "feature"`

**Commit message:** `feat(admin): add feature auction management with duration, position, and audit logging`

---

### 5.3 — Admin: dispute resolution queue

**Goal:** Add a structured dispute resolution section in the admin panel with a queue, evidence viewing, and decision workflow.

**Agent context:**
```
TASK: Create a dispute resolution system in BidFlow (Laravel 11).

1. Migration: database/migrations/YYYY_MM_DD_create_disputes_table.php
   Table: disputes
   Columns:
   - id, auction_id (FK cascade), claimant_id (FK users), respondent_id (FK users)
   - type: enum('item_not_received', 'not_as_described', 'non_payment', 'other')
   - description: text
   - status: enum('open','under_review','resolved_buyer','resolved_seller','closed') DEFAULT 'open'
   - resolution_notes: text nullable (admin fills this)
   - resolved_by: FK users nullable
   - resolved_at: timestamp nullable
   - evidence_urls: json nullable (array of file URLs)
   - created_at, updated_at

2. Model: app/Models/Dispute.php
   - Relationships: auction, claimant, respondent, resolver
   - Scopes: scopeOpen(), scopeUnderReview(), scopeResolved()
   - Status labels accessor

3. User-facing controller: app/Http/Controllers/DisputeController.php
   create(Auction $auction): auth required, must be buyer or seller of auction, auction must be completed
   store(Request $request, Auction $auction): validate type+description, create dispute
   Returns redirect to user.won-auctions with message

4. Admin controller: app/Http/Controllers/Admin/DisputeController.php
   index(): paginate disputes with filters (status, type), pass statusCounts
   show(Dispute $dispute): show all details
   update(Request $request, Dispute $dispute): 
     validate: status, resolution_notes required
     Update dispute: status, resolution_notes, resolved_by=auth()->id(), resolved_at=now()
     Notify both parties via database notification
     Log to AuditLog

5. Admin routes (inside admin middleware):
   GET    /admin/disputes         → index  → admin.disputes.index
   GET    /admin/disputes/{id}    → show   → admin.disputes.show
   PATCH  /admin/disputes/{id}    → update → admin.disputes.update

6. Admin views:
   admin/disputes/index.blade.php: table with status filter tabs (same pattern as admin/auctions/index)
   admin/disputes/show.blade.php: dispute details, timeline, resolution form

7. Add "Open Dispute" button to user/won-auctions.blade.php for completed, paid auctions.

8. Add "Disputes" link to admin navigation in layouts/navigation.blade.php.

Output all files completely.
```

**Acceptance criteria:**
- [ ] Only auction participants can open disputes
- [ ] Only completed auctions can have disputes
- [ ] Admin resolution notifies both parties
- [ ] AuditLog entry created on resolution
- [ ] Status filter tabs show counts

**Test command:** `php artisan route:list | grep "disputes" && php artisan migrate --pretend 2>&1 | tail -5`

**Commit message:** `feat(admin): add dispute resolution system with queue, evidence, and decision workflow`

---

## PHASE 6 — Performance & Quality
> Cross-cutting concerns: caching, N+1 prevention, accessibility, mobile optimization.
> **Estimated micro-tasks:** 5

---

### 6.1 — Fix N+1 queries across all controllers

**Goal:** Audit and fix all N+1 query patterns using eager loading. Add Laravel Telescope or query logging to catch regressions.

**Agent context:**
```
TASK: Fix N+1 query problems in BidFlow (Laravel 11).

Review and fix the following controllers by adding eager loading:

1. app/Http/Controllers/AuctionController.php index():
   Current: $auctions = Auction::active()->paginate(12)
   Fix:     ->with(['categories', 'media', 'brand', 'bids' => fn($q) => $q->select('auction_id', DB::raw('count(*) as bids_count'))->groupBy('auction_id')])
   Actually use withCount('bids') instead of the above pattern.
   Correct fix: Auction::active()->with(['primaryCategory', 'media', 'brand', 'tags'])->withCount('bids')->paginate(12)

2. app/Http/Controllers/AuctionController.php show():
   Fix: ->with(['seller', 'categories', 'media', 'brand', 'tags', 'attributeValues.attribute', 'highestBid.user', 'winner'])

3. app/Http/Controllers/Admin/AuctionManagementController.php index():
   Fix: ->with(['seller'])->withCount('bids')

4. app/Http/Controllers/Admin/UserManagementController.php index():
   Fix: ->withCount(['auctions', 'bids'])

5. app/Http/Controllers/CategoryBrowseController.php show():
   Fix: Auction eager loads on the auction query within the category

6. app/Http/Controllers/Seller/DashboardController.php index():
   Fix: $activeListings should use ->with(['media'])->withCount('bids')

After fixing, add the following to app/Providers/AppServiceProvider.php (ONLY in local environment):
  if (app()->isLocal()) {
    DB::listen(function ($query) {
      if (str_contains($query->sql, 'select') && $query->time > 100) {
        Log::warning('Slow query detected', ['sql' => $query->sql, 'time' => $query->time]);
      }
    });
  }

Output: all 6 modified controller files showing ONLY the changed query lines with surrounding context (10 lines above and below each change). Do not output entire files.
```

**Acceptance criteria:**
- [ ] Each modified query includes the required relationships
- [ ] `withCount` used instead of count subqueries where applicable
- [ ] `bids_count` available on Auction model via withCount (no `bid_count` ambiguity)
- [ ] Slow query logging added in AppServiceProvider (local only)

**Test command:** `php artisan route:list | head -5 && grep -rn "->with\[" app/Http/Controllers/ | wc -l`

**Commit message:** `perf: fix N+1 queries across auction, admin, and seller controllers with eager loading`

---

### 6.2 — Add skeleton loaders to auction index and show pages

**Goal:** Replace blank white flash during page load with skeleton screens for the auction grid and detail page.

**Agent context:**
```
TASK: Add skeleton loading screens to BidFlow auction pages (Tailwind + Alpine.js).

1. Create `resources/views/components/skeleton/auction-card.blade.php`:
   A skeleton version of the auction card with animate-pulse:
   - Gray rectangle for image area (aspect-video, bg-gray-200 dark:bg-gray-700)
   - Two gray lines for title (h-4 bg-gray-200, h-3 bg-gray-200 w-3/4)
   - One line for price (h-5 w-1/3 bg-gray-200)
   - One line for countdown (h-3 w-1/2 bg-gray-200)
   No props needed.

2. Create `resources/views/components/skeleton/auction-detail.blade.php`:
   A skeleton for the auction show page layout:
   - Left column: large image skeleton (aspect-video), 3 text line skeletons, a specs table skeleton
   - Right column: price skeleton (h-12 w-1/2), countdown skeleton, bid form skeleton (h-10)

3. Update resources/views/auctions/index.blade.php:
   When the progressive-enhancement filter fetch is loading (loading=true):
   Replace the auction grid with:
   <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
     @for($i = 0; $i < 6; $i++) <x-skeleton.auction-card /> @endfor
   </div>

4. For the initial page load (no JS), keep the server-rendered grid.
   Only the fetch-based refresh uses skeletons.

5. For the auction show page: add a minimal loading bar at the top of the page.
   Use NProgress.js pattern (thin colored bar at page top):
   Add to layouts/app.blade.php: a div with id="page-progress" positioned fixed top-0 left-0 h-0.5 bg-indigo-600 transition-all duration-300
   In app.js: on every fetch() start, set width to 30%, on complete set to 100%, then hide after 300ms.

Output: both skeleton components, the modified auctions/index.blade.php filter section, and the progress bar addition to app.blade.php + app.js.
```

**Acceptance criteria:**
- [ ] Skeleton cards have `animate-pulse` class
- [ ] Skeletons only shown during JS-initiated refreshes (not on initial server render)
- [ ] Progress bar appears and disappears correctly
- [ ] Dark mode classes on skeleton elements
- [ ] 6 skeleton cards shown during loading (matching the default page size)

**Test command:** `grep -c "animate-pulse" resources/views/components/skeleton/auction-card.blade.php`

**Commit message:** `feat(ux): add skeleton loaders for auction grid refresh and page progress bar`

---

### 6.3 — Mobile touch target and responsive fixes

**Goal:** Audit and fix all elements with insufficient touch targets on mobile. Enforce 44×44px minimum for all interactive elements.

**Agent context:**
```
TASK: Fix mobile touch target sizes across BidFlow views.

The following elements are known to be too small on mobile (< 44px touch area):
1. Pagination links — add min-w-[44px] min-h-[44px] and flex items-center justify-center
2. Filter clear buttons — ensure at least h-11 (44px) 
3. Bid history "Delete" and "Edit" text links in admin — wrap in a button with p-2
4. Tag delete buttons — currently small X icons — ensure 44px tap area
5. The "Watch" toggle button on auction show page — ensure minimum h-11

Audit these files and make the minimum changes:
- resources/views/vendor/pagination/tailwind.blade.php (publish first if not exists)
- resources/views/admin/auctions/show.blade.php (bid action buttons)
- resources/views/admin/users/show.blade.php (action buttons)
- resources/views/auctions/show.blade.php (watch button)
- resources/views/components/ui/button.blade.php (ensure min-h applied per size)

For x-ui-button sizes, ensure:
  sm: min-h-[36px] (acceptable for desktop, but if needed use md for mobile CTAs)
  md: min-h-[44px] — update this size to ensure min height
  lg: min-h-[52px]

Also add the following to resources/css/app.css:
  /* Ensure touch targets meet 44px minimum */
  @media (pointer: coarse) {
    .btn-sm { min-height: 44px; }
  }

Also fix: on mobile (<768px), the auction show page bid form input should be larger:
  input#bid-amount: add text-lg h-14 on mobile

Output: modified pagination blade (publish command), modified button component, modified auction show page bid input, and the CSS addition. Show only changed lines with context.
```

**Acceptance criteria:**
- [ ] Pagination links have min 44px touch target
- [ ] x-ui-button md size has `min-h-[44px]`
- [ ] `@media (pointer: coarse)` CSS added
- [ ] Bid amount input is larger on mobile

**Test command:** `grep -c "min-h-\[44px\]" resources/views/components/ui/button.blade.php`

**Commit message:** `fix(mobile): enforce 44px minimum touch targets across interactive elements`

---

### 6.4 — Accessibility: ARIA labels and keyboard navigation

**Goal:** Add ARIA attributes, keyboard shortcuts, and focus management for core auction actions.

**Agent context:**
```
TASK: Improve accessibility in BidFlow across key views.

1. resources/views/auctions/show.blade.php:
   - Add aria-label="Place bid on {$auction->title}" to the bid submit button
   - Add aria-live="polite" aria-atomic="true" to the #price-display element
   - Add role="status" to the countdown element
   - Add aria-describedby pointing to the countdown on the bid form
   - Add aria-label="Auction images" to the gallery container

2. resources/views/auctions/index.blade.php:
   - Add aria-label="Auction listings, {$auctions->total()} results" to the main grid
   - Add role="search" and aria-label="Filter auctions" to the filter form

3. resources/views/components/ui/button.blade.php:
   - When $loading is true, add aria-busy="true" and aria-label="Loading..."
   - When $disabled is true, add aria-disabled="true"

4. resources/views/layouts/app.blade.php:
   - Add a skip-to-main-content link as the very first element in <body>:
     <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-white focus:text-indigo-600 focus:rounded-lg focus:shadow-lg">
       Skip to main content
     </a>
   - Add id="main-content" to the <main> tag

5. Keyboard shortcuts — add to resources/js/app.js:
   document.addEventListener('keydown', (e) => {
     // Only when no input/textarea is focused
     if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
     if (e.key === 'b' && !e.metaKey && !e.ctrlKey) {
       const bidInput = document.getElementById('bid-amount');
       if (bidInput) { bidInput.focus(); bidInput.select(); }
     }
     if (e.key === 'w' && !e.metaKey && !e.ctrlKey) {
       const watchBtn = document.getElementById('watch-btn');
       if (watchBtn) watchBtn.click();
     }
   });

6. Add a visible keyboard shortcut hint on the auction show page:
   Below the bid button: <p class="text-xs text-gray-400 text-center mt-1">Press B to focus bid input</p>

Output: all 5 modified files showing ONLY changed sections with surrounding context.
```

**Acceptance criteria:**
- [ ] `aria-live="polite"` on price display
- [ ] Skip link present and focusable in app layout
- [ ] Keyboard shortcut B focuses bid input on auction show page
- [ ] Loading button has `aria-busy="true"`
- [ ] `role="search"` on filter form

**Test command:** `grep -c "aria-live\|aria-label\|role=" resources/views/auctions/show.blade.php`

**Commit message:** `fix(a11y): add ARIA labels, keyboard shortcuts, skip link, and live regions`

---

### 6.5 — Cache warming and config optimization

**Goal:** Add cache warming for expensive queries, proper cache tags, and ensure production config is optimized.

**Agent context:**
```
TASK: Add cache warming and optimization to BidFlow (Laravel 11).

1. Create app/Console/Commands/WarmCache.php:
   Signature: cache:warm
   Description: Warm up application caches for performance
   
   handle() method warms:
   a. Category tree: CategoryService->getTree() → cache 'category_tree' TTL 3600
   b. Featured auctions: Auction::featured()->with('media')->take(8)->get() → 'featured_auctions' TTL 300
   c. Root categories with auction counts: Category::root()->with('children')->withCount('auctions')->get() → 'root_categories' TTL 1800
   d. Live auction count: Auction::active()->count() → 'live_auction_count' TTL 60
   e. Output: $this->info("Cache warmed: {key} ({count}ms)")

2. Register the command in app/Console/Kernel.php (or routes/console.php if using Laravel 11 style):
   Schedule: ->everyFiveMinutes() for featured_auctions
   Schedule: ->hourly() for category_tree and root_categories

3. Update all places that query featured auctions to use the cache key:
   In routes/web.php (homepage), in any controller fetching featured:
   Replace direct queries with Cache::remember('featured_auctions', 300, fn() => ...)

4. Add cache invalidation: when an Auction is updated and is_featured changes:
   In Auction model boot(), add static::updated() observer that forgets 'featured_auctions' cache key.

5. Update config/cache.php to ensure the 'file' driver is used as fallback:
   No change if Redis is configured. Just add a comment documenting the cache keys used.

6. Add to composer.json scripts:
   "post-deploy": ["@php artisan cache:warm", "@php artisan route:cache", "@php artisan view:cache"]

Output: WarmCache command, routes/console.php additions, Auction model boot() change, and relevant controller snippets.
```

**Acceptance criteria:**
- [ ] `php artisan cache:warm` runs without errors
- [ ] All 4 cache keys populated after running
- [ ] `featured_auctions` cache invalidated when Auction.is_featured changes
- [ ] Scheduled in console.php with correct frequency

**Test command:** `php artisan cache:warm && php artisan schedule:list | grep "cache:warm"`

**Commit message:** `perf: add cache warming command, scheduled invalidation, and production optimization`

---

## APPENDIX A — Agent Workflow Protocol

When running agents on these tasks, follow this protocol for every micro-task:

```
STEP 1 — Prepare
  git checkout -b feature/{task-id}-{short-description}
  Paste the "Agent context" block as the first message to the agent.
  Include relevant existing file contents as context if the task modifies existing files.

STEP 2 — Generate
  Agent produces all file contents.
  Review: does the output match the acceptance criteria checklist? 

STEP 3 — Apply
  Write each file to disk.
  Run: php artisan view:clear && php artisan route:clear && php artisan config:clear

STEP 4 — Test
  Run the "Test command" from the task.
  Run: php artisan test --filter={relevant test class} if tests exist.
  Open the browser and visually verify.

STEP 5 — Commit
  git add -A
  git commit -m "{exact commit message from task}"
  git push origin feature/{task-id}-...

STEP 6 — Move to next
  Start the next task. Parallelism notes specify which tasks can run simultaneously.
```

---

## APPENDIX B — Parallelism Map

```
Phase 1:   [1.1] [1.2] [1.3] [1.4] ← run simultaneously
           then [1.5] [1.6] [1.7] [1.8] [1.9] ← run simultaneously (depend on 1.1-1.4)
           then [1.10] [1.11] [1.12] ← run simultaneously (DB/backend, independent)

Phase 2:   [2.1] [2.2] [2.3] ← run simultaneously (depend on Phase 1)
           [2.4] depends on [2.3] ← run after
           [2.5] [2.6] ← depend on Phase 1 only, run simultaneously with 2.1-2.3
           [2.7] [2.8] ← independent backend, run simultaneously

Phase 3:   [3.1] depends on Phase 2.4
           [3.2] [3.3] [3.4] [3.5] ← run simultaneously after Phase 2

Phase 4:   [4.1] [4.2] [4.3] ← run simultaneously
           [4.4] depends on [4.1]

Phase 5:   [5.1] [5.2] [5.3] ← run simultaneously

Phase 6:   All can run in parallel (they are improvements, not new features)
           Run [6.1] first (N+1 fixes affect all pages), then [6.2]-[6.5] simultaneously
```

---

## APPENDIX C — Commit Convention

```
feat(scope):   new user-visible functionality
fix(scope):    bug fix
refactor(scope): code change, no behaviour change
perf(scope):   performance improvement
feat(db):      migration / model change
feat(admin):   admin panel feature
feat(seller):  seller portal feature
feat(ui):      new Blade component
feat(js):      new JavaScript module
feat(ux):      UX improvement (animation, feedback, empty state)
fix(a11y):     accessibility fix
fix(mobile):   mobile-specific fix

Scopes: auctions, bidding, dashboard, admin, seller, alerts, ratings, qa, ui, js, db, layout, home, mobile, a11y, perf, cache
```

---

*Total micro-tasks: 52*
*Estimated parallelised calendar time (3 agents): ~6 weeks*
*Estimated sequential time (1 agent): ~14 weeks*