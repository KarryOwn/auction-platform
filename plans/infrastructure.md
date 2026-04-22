# Infrastructure, API & Testing — Implementation Plan

> Codebase baseline: Laravel 12, PostgreSQL, Redis (Lua atomic engine), Reverb WebSockets, Horizon, Stripe, Sail (Docker), Pest testing framework.

---

## Feature 193 — Public REST API for Auction Listings

### A. Feature Overview
Expose a versioned, authenticated REST API (`/api/v1/`) for external developers to integrate with the platform. Covers: listing auctions, browsing categories, retrieving auction detail, placing bids, watching auctions, and reading user wallet state. Follows JSON:API-adjacent conventions.

**Business goal:** Third-party integrations (mobile apps, price-tracking services, affiliates); platform ecosystem growth; future mobile app client.

---

### B. Current State
- `routes/api.php` has a handful of internal routes (`/api/categories/tree`, `/api/tags/search`, `/api/stress-test/bid`).
- Laravel Sanctum is installed (`HasApiTokens` on `User` model).
- No versioned API, no rate limiting beyond the stress-test route, no external API documentation.
- `AuctionController::liveState()` returns a partial JSON snapshot — partially reusable.

---

### C. Required Changes

#### 1. Database
No new tables. API tokens are managed by Sanctum's `personal_access_tokens` table (already migrated).

Add token scopes tracking:

```php
// No migration needed — Sanctum stores abilities as JSON in personal_access_tokens.abilities
// Scopes defined as constants in a central class
```

#### 2. Backend Logic

**`app/Http/Resources/Api/V1/`** — new directory for API resources:

**`AuctionResource.php`:**

```php
namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AuctionResource extends JsonResource
{
    public function toArray($request): array
    {
        $isOwner = $request->user()?->id === $this->user_id;

        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'description'      => $this->description,
            'status'           => $this->status,
            'current_price'    => (float) $this->current_price,
            'starting_price'   => (float) $this->starting_price,
            'reserve_met'      => $this->reserve_met,
            'reserve_price'    => ($this->reserve_price_visible || $isOwner)
                                   ? (float) $this->reserve_price : null,
            'min_bid_increment'=> (float) $this->min_bid_increment,
            'next_minimum_bid' => (float) $this->minimumNextBid(),
            'bid_count'        => $this->bid_count,
            'currency'         => $this->currency,
            'condition'        => $this->condition,
            'condition_label'  => $this->condition_label,
            'end_time'         => $this->end_time?->toIso8601String(),
            'time_remaining'   => $this->timeRemaining(),
            'seller'           => [
                'id'         => $this->user_id,
                'name'       => $this->seller?->name,
                'seller_slug'=> $this->seller?->seller_slug,
            ],
            'brand'            => $this->whenLoaded('brand', fn () => [
                'id'   => $this->brand->id,
                'name' => $this->brand->name,
            ]),
            'categories'       => $this->whenLoaded('categories', fn () =>
                $this->categories->map(fn ($c) => [
                    'id'         => $c->id,
                    'name'       => $c->name,
                    'slug'       => $c->slug,
                    'is_primary' => (bool) $c->pivot->is_primary,
                ])
            ),
            'images'           => $this->getMedia('images')->map(fn ($m) => [
                'thumbnail' => $m->getUrl('thumbnail'),
                'gallery'   => $m->getUrl('gallery'),
                'full'      => $m->getUrl('full'),
            ]),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
            'links'            => [
                'self' => route('api.v1.auctions.show', $this->id),
            ],
        ];
    }
}
```

**`BidResource.php`:**

```php
// Minimal bid representation for API consumers
// id, auction_id, amount, bid_type, is_snipe_bid, created_at
```

**`app/Http/Controllers/Api/V1/`** — new controller namespace:

**`AuctionController.php`:**

```php
namespace App\Http\Controllers\Api\V1;

use App\Contracts\BiddingStrategy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuctionResource;
use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuctionController extends Controller
{
    public function __construct(protected BiddingStrategy $engine) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Auction::active()
            ->with(['seller:id,name,seller_slug', 'media', 'brand', 'categories'])
            ->withCount('bids');

        if ($q = $request->input('q')) {
            $query->where('title', 'ilike', "%{$q}%");
        }
        if ($category = $request->input('category_id')) {
            $query->whereHas('categories', fn ($cq) => $cq->where('categories.id', $category));
        }
        if ($min = $request->input('min_price')) {
            $query->where('current_price', '>=', (float) $min);
        }
        if ($max = $request->input('max_price')) {
            $query->where('current_price', '<=', (float) $max);
        }

        $sort = $request->input('sort', 'ending_soon');
        $query->when($sort === 'ending_soon', fn ($q) => $q->orderBy('end_time'))
              ->when($sort === 'newest', fn ($q) => $q->orderByDesc('created_at'))
              ->when($sort === 'price_asc', fn ($q) => $q->orderBy('current_price'))
              ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('current_price'));

        $auctions = $query->paginate(min((int) $request->input('per_page', 15), 50));

        // Sync live prices
        $auctions->each(fn ($a) => $a->current_price = $this->engine->getCurrentPrice($a));

        return AuctionResource::collection($auctions);
    }

    public function show(Auction $auction): AuctionResource
    {
        $auction->load(['seller', 'media', 'brand', 'categories', 'attributeValues.attribute']);
        $auction->current_price = $this->engine->getCurrentPrice($auction);
        return new AuctionResource($auction);
    }
}
```

**`BidController.php`:**

```php
namespace App\Http\Controllers\Api\V1;

use App\Contracts\BiddingStrategy;
use App\Exceptions\BidValidationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BidResource;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function __construct(protected BiddingStrategy $engine) {}

    public function store(Request $request, Auction $auction): JsonResponse
    {
        $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);

        // Sanctum ability check
        abort_unless($request->user()->tokenCan('bids:place'), 403, 'Token lacks bids:place scope.');

        try {
            $bid = $this->engine->placeBid(
                $auction, $request->user(), (float) $request->input('amount'),
                ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]
            );

            return response()->json([
                'data' => new BidResource($bid),
                'meta' => ['new_price' => (float) $bid->amount],
            ], 201);

        } catch (BidValidationException $e) {
            return $e->render();
        }
    }
}
```

**`AuthController.php`** — token issuance:

```php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function token(Request $request)
    {
        $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required'],
            'device_name' => ['required', 'string', 'max:100'],
            'abilities'   => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in(self::VALID_ABILITIES)],
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if ($user->isBanned()) {
            return response()->json(['error' => 'Account banned.'], 403);
        }

        $abilities = $request->input('abilities', ['auctions:read', 'bids:read']);

        $token = $user->createToken($request->device_name, $abilities);

        return response()->json([
            'token'       => $token->plainTextToken,
            'token_type'  => 'Bearer',
            'abilities'   => $abilities,
        ]);
    }

    public function revoke(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Token revoked.']);
    }

    private const VALID_ABILITIES = [
        'auctions:read', 'auctions:write',
        'bids:read', 'bids:place',
        'watchlist:read', 'watchlist:write',
        'wallet:read',
        'profile:read',
    ];
}
```

**Rate limiting** — `bootstrap/app.php` or `RouteServiceProvider`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(60)->by($request->user()->id)
        : Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('api-bids', function (Request $request) {
    return Limit::perMinute(20)->by($request->user()?->id ?? $request->ip());
});
```

**`routes/api.php`** — restructure:

```php
// V1 API — versioned prefix
Route::prefix('v1')->name('api.v1.')->middleware(['throttle:api'])->group(function () {

    // Auth — public
    Route::post('/auth/token',  [Api\V1\AuthController::class, 'token'])->name('auth.token');
    Route::delete('/auth/token',[Api\V1\AuthController::class, 'revoke'])->middleware('auth:sanctum')->name('auth.revoke');

    // Auctions — public read
    Route::get('/auctions',         [Api\V1\AuctionController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/{auction}',[Api\V1\AuctionController::class, 'show'])->name('auctions.show');

    // Categories — public read
    Route::get('/categories',        [Api\V1\CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category}',[Api\V1\CategoryController::class, 'show'])->name('categories.show');

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function () {
        // Bids
        Route::post('/auctions/{auction}/bids', [Api\V1\BidController::class, 'store'])
            ->middleware('throttle:api-bids')->name('bids.store');
        Route::get('/auctions/{auction}/bids',  [Api\V1\BidController::class, 'index'])->name('bids.index');

        // Watchlist
        Route::post('/auctions/{auction}/watch',  [Api\V1\WatchController::class, 'toggle'])->name('watch.toggle');

        // Profile & Wallet (read-only)
        Route::get('/me',              [Api\V1\ProfileController::class, 'show'])->name('profile.show');
        Route::get('/me/bids',         [Api\V1\ProfileController::class, 'bids'])->name('profile.bids');
        Route::get('/me/wallet',       [Api\V1\ProfileController::class, 'wallet'])->name('profile.wallet');
        Route::get('/me/notifications',[Api\V1\ProfileController::class, 'notifications'])->name('profile.notifications');
    });
});
```

#### 3. Error Response Standardisation

Create `app/Exceptions/ApiException.php` and ensure all API routes return consistent JSON errors:

```php
// In bootstrap/app.php withExceptions()
$exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
    if ($request->is('api/*')) {
        return response()->json(['error' => 'Unauthenticated.', 'code' => 401], 401);
    }
});

$exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
    if ($request->is('api/*')) {
        return response()->json([
            'error'   => 'Validation failed.',
            'code'    => 422,
            'details' => $e->errors(),
        ], 422);
    }
});
```

---

### D. Dependencies & Risks
- **Sanctum `auth:sanctum` middleware** is already installed but not applied to any routes — straightforward to add.
- **Breaking existing API routes:** The internal routes in `routes/api.php` (`/stress-test/bid`, `/categories/tree`, etc.) must remain at their existing paths. The new V1 API lives under `/api/v1/` — no conflicts.
- **Pagination format:** Use Laravel's default `LengthAwarePaginator` which serialises to `{data: [...], links: {...}, meta: {...}}` — compatible with most API consumers.

---

### E. Implementation Steps
1. Create `app/Http/Resources/Api/V1/` directory with `AuctionResource`, `BidResource`, `CategoryResource`, `UserResource`.
2. Create `app/Http/Controllers/Api/V1/` directory with `AuthController`, `AuctionController`, `BidController`, `CategoryController`, `WatchController`, `ProfileController`.
3. Configure rate limiters in `bootstrap/app.php`.
4. Restructure `routes/api.php` — add V1 prefix group without breaking existing routes.
5. Add standard JSON error rendering in `bootstrap/app.php`.
6. Write API feature tests (Pest) for all public and authenticated endpoints.
7. Verify Sanctum token abilities are enforced on write endpoints.

---

### F. Complexity & Priority
- **Complexity:** Medium (repetitive but thorough)
- **Priority:** Growth

---

## Feature 194 — API Documentation (Swagger / OpenAPI)

### A. Feature Overview
Generate and host interactive OpenAPI 3.0 documentation for the public REST API at `/api/docs`. Developers can explore endpoints, test requests, and view schemas.

**Business goal:** Developer experience; reduces integration support burden; attracts ecosystem integrators.

---

### B. Current State
- No API documentation exists.
- `darkaonline/l5-swagger` is the standard Laravel OpenAPI package (not installed).

---

### C. Required Changes

#### 1. Backend Logic

**Install package:**

```bash
composer require darkaonline/l5-swagger
sail artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

**`config/l5-swagger.php`** — configure:

```php
'default' => 'v1',
'documentations' => [
    'v1' => [
        'api' => [
            'title'   => 'Auction Platform API v1',
            'version' => '1.0.0',
        ],
        'routes' => [
            'api'  => 'api/v1/documentation',
            'docs' => 'api/v1/docs',
        ],
        'paths' => [
            'annotations' => base_path('app/Http/Controllers/Api/V1'),
        ],
    ],
],
```

**Annotate controllers with OpenAPI docblocks** — example for `AuctionController`:

```php
/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Auction Platform API",
 *     description="Public REST API for auction platform integration.",
 *     @OA\Contact(email="api@example.com")
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token"
 * )
 */

/**
 * @OA\Get(
 *     path="/api/v1/auctions",
 *     summary="List active auctions",
 *     tags={"Auctions"},
 *     @OA\Parameter(name="q", in="query", description="Search keyword", @OA\Schema(type="string")),
 *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="min_price", in="query", @OA\Schema(type="number")),
 *     @OA\Parameter(name="max_price", in="query", @OA\Schema(type="number")),
 *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"ending_soon","newest","price_asc","price_desc"})),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", maximum=50)),
 *     @OA\Response(response=200, description="Paginated auction list",
 *         @OA\JsonContent(ref="#/components/schemas/AuctionCollection")
 *     )
 * )
 */
public function index(Request $request): AnonymousResourceCollection { ... }
```

**Generate docs:**

```bash
sail artisan l5-swagger:generate
```

Add to `routes/console.php`:

```php
// Regenerate API docs on deploy
Schedule::command('l5-swagger:generate')->weekly()->name('regenerate-api-docs');
```

#### 2. Routes (auto-configured by l5-swagger)
The package auto-registers `/api/v1/documentation` (Swagger UI) and `/api/v1/docs` (raw JSON spec).

Protect Swagger UI in production:

```php
// config/l5-swagger.php
'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false), // false in production
```

Add middleware to swagger routes in non-local environments:

```php
// config/l5-swagger.php
'middleware' => [
    'api'  => app()->isProduction() ? ['auth:sanctum'] : [],
    'docs' => [],
],
```

---

### D. Dependencies & Risks
- `darkaonline/l5-swagger` requires `zircote/swagger-php` — compatible with PHP 8.x.
- Documentation annotations are verbose but maintainable alongside controller code.
- **Alternative:** Use Scribe (`knuckleswtf/scribe`) which auto-generates docs from routes and request classes without annotations — lower maintenance overhead. Recommended if annotation overhead is unacceptable.

---

### E. Implementation Steps
1. `composer require darkaonline/l5-swagger`.
2. Publish and configure `config/l5-swagger.php`.
3. Add OpenAPI annotations to all V1 controllers (start with auctions and auth).
4. Add schema definitions for `AuctionResource`, `BidResource`, error responses.
5. Run `php artisan l5-swagger:generate`.
6. Verify Swagger UI at `/api/v1/documentation`.
7. Add weekly regeneration schedule.

---

### F. Complexity & Priority
- **Complexity:** Low (annotation-heavy but mechanical)
- **Priority:** Growth

---

## Feature 195 — Webhook Support (Outbound Events)

### A. Feature Overview
Allow external integrators (third-party apps, partner systems) to register webhook endpoints. The platform delivers HTTP POST payloads when key events occur: bid placed, auction ended, auction cancelled, payment captured.

**Business goal:** Enables real integrations without polling; complements the public API (Feature 193); standard for mature marketplaces (Stripe model).

---

### B. Current State
- `StripeWebhookController` handles *incoming* Stripe webhooks.
- The platform dispatches *internal* events (`BidPlaced`, `AuctionClosed`, `AuctionCancelled`) via Laravel's event system.
- No mechanism to forward these to external HTTP endpoints.

---

### C. Required Changes

#### 1. Database

```php
Schema::create('webhook_endpoints', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    // NULL = platform-wide (admin-configured); non-null = seller-specific
    $table->string('url', 2048);
    $table->string('secret', 64);              // HMAC signing secret
    $table->json('events');                    // ['bid.placed', 'auction.closed', ...]
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_triggered_at')->nullable();
    $table->unsignedInteger('failure_count')->default(0);
    $table->timestamps();

    $table->index(['user_id', 'is_active']);
});

Schema::create('webhook_deliveries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
    $table->string('event_type', 50);
    $table->json('payload');
    $table->string('status', 20)->default('pending'); // pending, delivered, failed
    $table->unsignedSmallInteger('http_status')->nullable();
    $table->text('response_body')->nullable();
    $table->unsignedInteger('attempt_count')->default(0);
    $table->timestamp('next_retry_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'next_retry_at']);
    $table->index(['webhook_endpoint_id', 'status']);
});
```

#### 2. Backend Logic

**`app/Models/WebhookEndpoint.php`** and **`WebhookDelivery.php`** — standard Eloquent models.

**`app/Services/WebhookDispatchService.php`** — new service:

```php
namespace App\Services;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatchService
{
    /**
     * Queue delivery of an event to all matching endpoints.
     */
    public function dispatch(string $eventType, array $payload, ?int $userId = null): void
    {
        $endpoints = WebhookEndpoint::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id');         // platform-wide
                if ($userId) {
                    $q->orWhere('user_id', $userId); // user-specific
                }
            })
            ->whereJsonContains('events', $eventType)
            ->get();

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event_type'          => $eventType,
                'payload'             => $payload,
                'status'              => 'pending',
                'next_retry_at'       => now(),
            ]);

            \App\Jobs\DeliverWebhook::dispatch($delivery->id)->onQueue('default');
        }
    }

    /**
     * Deliver a single webhook delivery with HMAC signing.
     */
    public function deliver(WebhookDelivery $delivery): bool
    {
        $endpoint = $delivery->webhookEndpoint;
        $payload  = json_encode($delivery->payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'          => 'application/json',
                    'X-Webhook-Event'       => $delivery->event_type,
                    'X-Webhook-Timestamp'   => $timestamp,
                    'X-Webhook-Signature'   => "t={$timestamp},v1={$signature}",
                    'X-Webhook-Delivery-Id' => $delivery->id,
                ])
                ->post($endpoint->url, $delivery->payload);

            $success = $response->status() >= 200 && $response->status() < 300;

            $delivery->update([
                'status'        => $success ? 'delivered' : 'failed',
                'http_status'   => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'attempt_count' => $delivery->attempt_count + 1,
                'next_retry_at' => $success ? null : $this->nextRetryAt($delivery->attempt_count + 1),
            ]);

            if ($success) {
                $endpoint->update([
                    'last_triggered_at' => now(),
                    'failure_count'     => 0,
                ]);
            } else {
                $endpoint->increment('failure_count');
                // Disable after 10 consecutive failures
                if ($endpoint->failure_count >= 10) {
                    $endpoint->update(['is_active' => false]);
                    Log::warning('WebhookDispatchService: endpoint disabled after failures', [
                        'endpoint_id' => $endpoint->id,
                        'url'         => $endpoint->url,
                    ]);
                }
            }

            return $success;

        } catch (\Throwable $e) {
            $delivery->update([
                'status'        => 'failed',
                'attempt_count' => $delivery->attempt_count + 1,
                'next_retry_at' => $this->nextRetryAt($delivery->attempt_count + 1),
            ]);
            return false;
        }
    }

    private function nextRetryAt(int $attempt): \Carbon\Carbon
    {
        // Exponential backoff: 1m, 5m, 30m, 2h, 8h
        $delayMinutes = [1, 5, 30, 120, 480][$attempt - 1] ?? 480;
        return now()->addMinutes($delayMinutes);
    }
}
```

**`app/Jobs/DeliverWebhook.php`** — queued delivery job:

```php
namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries   = 5;
    public array $backoff = [60, 300, 1800, 7200, 28800]; // seconds

    public function __construct(public int $deliveryId) {}

    public function handle(WebhookDispatchService $service): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if (! $delivery || $delivery->status === 'delivered') return;

        $service->deliver($delivery);
    }
}
```

**Retry scheduler** — pick up failed deliveries that are due for retry:

```php
// routes/console.php
Schedule::call(function () {
    WebhookDelivery::where('status', 'failed')
        ->where('attempt_count', '<', 5)
        ->where('next_retry_at', '<=', now())
        ->get()
        ->each(fn ($d) => DeliverWebhook::dispatch($d->id));
})->everyFiveMinutes()->name('retry-webhook-deliveries');
```

**Hook into existing events** — the cleanest integration is in existing listeners, or via a dedicated listener:

```php
// app/Listeners/DispatchWebhooksListener.php
// Listens to BidPlaced, AuctionClosed, AuctionCancelled

use App\Events\BidPlaced;
use App\Events\AuctionClosed;
use App\Events\AuctionCancelled;
use App\Services\WebhookDispatchService;

class DispatchWebhooksListener
{
    public function __construct(protected WebhookDispatchService $service) {}

    public function handleBidPlaced(BidPlaced $event): void
    {
        $this->service->dispatch('bid.placed', [
            'auction_id'  => $event->auctionId,
            'bid_id'      => $event->bid->id,
            'amount'      => $event->amount,
            'bidder_id'   => $event->bidderId,
            'created_at'  => now()->toIso8601String(),
        ], $event->auction->user_id); // notify seller's webhooks
    }

    public function handleAuctionClosed(AuctionClosed $event): void
    {
        $this->service->dispatch('auction.closed', $event->broadcastWith(), $event->auction->user_id);
    }

    public function handleAuctionCancelled(AuctionCancelled $event): void
    {
        $this->service->dispatch('auction.cancelled', [
            'auction_id' => $event->auction->id,
            'title'      => $event->auction->title,
            'reason'     => $event->reason,
        ], $event->auction->user_id);
    }
}
```

Register in `EventServiceProvider` or `AppServiceProvider`:

```php
Event::listen(BidPlaced::class, [DispatchWebhooksListener::class, 'handleBidPlaced']);
Event::listen(AuctionClosed::class, [DispatchWebhooksListener::class, 'handleAuctionClosed']);
Event::listen(AuctionCancelled::class, [DispatchWebhooksListener::class, 'handleAuctionCancelled']);
```

**Seller/Admin webhook management:**

```php
// app/Http/Controllers/Api/V1/WebhookController.php
// index(): list user's endpoints; store(): create endpoint; destroy(): delete; redeliver(): retry delivery
```

#### 3. API / Routes

```php
// Inside auth:sanctum middleware group in V1 API
Route::prefix('/webhooks')->name('api.v1.webhooks.')->group(function () {
    Route::get('/',           [WebhookController::class, 'index'])->name('index');
    Route::post('/',          [WebhookController::class, 'store'])->name('store');
    Route::delete('/{endpoint}', [WebhookController::class, 'destroy'])->name('destroy');
    Route::get('/deliveries', [WebhookController::class, 'deliveries'])->name('deliveries');
    Route::post('/deliveries/{delivery}/redeliver', [WebhookController::class, 'redeliver'])->name('redeliver');
});
```

---

### D. Dependencies & Risks
- **SSRF protection:** Never allow webhook URLs pointing to internal IPs (`127.0.0.1`, `10.x.x.x`, `172.16.x.x`). Validate URL in `WebhookController::store()` — use `filter_var($url, FILTER_VALIDATE_URL)` + IP range check.
- **Payload size:** Keep payloads lean — IDs and essential fields only. Do not include user PII in webhook payloads.
- **Security — HMAC verification:** Document the signature verification process in API docs (Feature 194) so integrators can verify authenticity.

---

### E. Implementation Steps
1. Migrations: create `webhook_endpoints`, `webhook_deliveries`.
2. Create `WebhookEndpoint`, `WebhookDelivery` models.
3. Create `WebhookDispatchService`.
4. Create `DeliverWebhook` job.
5. Create `DispatchWebhooksListener` and register event listeners.
6. Create API `WebhookController`.
7. Add retry scheduler.
8. Add SSRF URL validation to endpoint creation.
9. Register routes.
10. Write tests: delivery success, retry logic, HMAC signature, SSRF rejection, endpoint disable after failures.

---

### F. Complexity & Priority
- **Complexity:** High
- **Priority:** Growth

---

## Feature 200 — Calendar Integration (Add Auction End to Calendar)

### A. Feature Overview
Users can add an auction's end time to their calendar via: iCalendar (`.ics`) download, "Add to Google Calendar" link, and "Add to Apple Calendar" link.

**Business goal:** Reduces missed auction endings; drives return visits; passive marketing (calendar shows your platform name).

---

### B. Current State
- `Auction` model has `end_time` (Carbon datetime).
- No `.ics` generation or calendar link generation exists.

---

### C. Required Changes

#### 1. Database
No changes needed.

#### 2. Backend Logic

**`app/Http/Controllers/AuctionCalendarController.php`** — new controller:

```php
namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Response;

class AuctionCalendarController extends Controller
{
    /**
     * Generate and serve an iCalendar (.ics) file for an auction.
     */
    public function ics(Auction $auction): Response
    {
        abort_unless(in_array($auction->status, [Auction::STATUS_ACTIVE, Auction::STATUS_DRAFT]), 404);

        $uid     = "auction-{$auction->id}@" . parse_url(config('app.url'), PHP_URL_HOST);
        $dtStamp = now()->format('Ymd\THis\Z');
        $dtEnd   = $auction->end_time->utc()->format('Ymd\THis\Z');
        $dtStart = $auction->end_time->utc()->subHour()->format('Ymd\THis\Z');
        $summary = addcslashes("Auction Ends: {$auction->title}", ',;\\');
        $url     = route('auctions.show', $auction);
        $desc    = addcslashes(
            "Current price: \${$auction->current_price}. Ends at {$auction->end_time->format('H:i T')}.\n{$url}",
            ',;\\',
        );

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//AuctionPlatform//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "DTSTART:{$dtStart}",
            "DTEND:{$dtEnd}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$desc}",
            "URL:{$url}",
            'BEGIN:VALARM',
            'TRIGGER:-PT30M',
            'ACTION:DISPLAY',
            "DESCRIPTION:Auction ending in 30 minutes: {$auction->title}",
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        return response($ics, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"auction-{$auction->id}.ics\"",
        ]);
    }

    /**
     * Generate a Google Calendar add URL.
     */
    public function googleCalendarUrl(Auction $auction): \Illuminate\Http\JsonResponse
    {
        $params = http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => "Auction Ends: {$auction->title}",
            'dates'    => $auction->end_time->utc()->subHour()->format('Ymd\THis\Z')
                        . '/' . $auction->end_time->utc()->format('Ymd\THis\Z'),
            'details'  => "Current price: \${$auction->current_price}\n" . route('auctions.show', $auction),
            'location' => route('auctions.show', $auction),
        ]);

        return response()->json([
            'google_calendar_url' => "https://calendar.google.com/calendar/render?{$params}",
            'ics_url'             => route('auctions.calendar.ics', $auction),
        ]);
    }
}
```

#### 3. Routes

```php
Route::get('/auctions/{auction}/calendar.ics',      [AuctionCalendarController::class, 'ics'])->name('auctions.calendar.ics');
Route::get('/auctions/{auction}/calendar/google',   [AuctionCalendarController::class, 'googleCalendarUrl'])->name('auctions.calendar.google');
```

#### 4. Frontend (contract)
- On auction detail page: "Add to Calendar" dropdown with three options: Download .ics, Add to Google Calendar, Add to Apple Calendar (same .ics URL).
- The `.ics` file includes a 30-minute alarm reminder.

---

### E. Implementation Steps
1. Create `AuctionCalendarController` with `ics()` and `googleCalendarUrl()`.
2. Register routes (no auth required — calendar links should be shareable).
3. Write test validating `.ics` content-type and basic iCalendar structure.

---

### F. Complexity & Priority
- **Complexity:** Low
- **Priority:** Growth

---

## Feature 203 — Elasticsearch Integration for Search

### A. Feature Overview
Replace the current `ILIKE` full-text search in `AuctionController::index()` with Elasticsearch for relevance ranking, fuzzy matching, attribute filtering, and sub-100ms search response times.

**Business goal:** Scalable search as auction count grows; better relevance; enables autocomplete and faceted filtering.

---

### B. Current State
- Search uses `ILIKE "%keyword%"` on `title` and seller name — works for small datasets, degrades at scale.
- `CategoryBrowseController::show()` uses `whereHas()` + `ILIKE` for category search.
- No search indexing infrastructure exists.

---

### C. Required Changes

#### 1. Database
No Postgres changes. Elasticsearch index holds denormalised auction documents.

#### 2. Backend Logic

**Package:** Use `elastic/elasticsearch-php` (official) or `laravel-scout` with the `babenkoivan/scout-elasticsearch-driver` driver.

**Recommended approach: Laravel Scout with Elasticsearch driver:**

```bash
composer require laravel/scout
composer require babenkoivan/scout-elasticsearch-driver
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

**`config/scout.php`:**

```php
'driver' => env('SCOUT_DRIVER', 'database'), // 'elasticsearch' in production
'elasticsearch' => [
    'index-configurators' => [
        \App\Search\AuctionIndexConfigurator::class,
    ],
    'mappings' => [
        \App\Models\Auction::class => [
            'configurator' => \App\Search\AuctionIndexConfigurator::class,
        ],
    ],
],
```

**`app/Search/AuctionIndexConfigurator.php`** — Elasticsearch index settings:

```php
namespace App\Search;

use ScoutElastic\IndexConfigurator;
use ScoutElastic\Migratable;

class AuctionIndexConfigurator extends IndexConfigurator
{
    use Migratable;

    protected array $settings = [
        'analysis' => [
            'analyzer' => [
                'auction_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter'    => ['lowercase', 'asciifolding', 'stop'],
                ],
            ],
        ],
    ];

    protected array $defaultMapping = [
        'properties' => [
            'title'         => ['type' => 'text', 'analyzer' => 'auction_analyzer', 'boost' => 3],
            'description'   => ['type' => 'text', 'analyzer' => 'auction_analyzer'],
            'seller_name'   => ['type' => 'text', 'analyzer' => 'auction_analyzer'],
            'current_price' => ['type' => 'double'],
            'status'        => ['type' => 'keyword'],
            'end_time'      => ['type' => 'date'],
            'condition'     => ['type' => 'keyword'],
            'brand_name'    => ['type' => 'keyword'],
            'category_ids'  => ['type' => 'integer'],
            'category_slugs'=> ['type' => 'keyword'],
            'tag_slugs'     => ['type' => 'keyword'],
            'attributes'    => ['type' => 'nested', 'properties' => [
                'slug'  => ['type' => 'keyword'],
                'value' => ['type' => 'keyword'],
            ]],
        ],
    ];
}
```

**`app/Models/Auction.php`** — add Scout trait and `toSearchableArray()`:

```php
use Laravel\Scout\Searchable;

// In class body:
use Searchable;

public function toSearchableArray(): array
{
    $this->loadMissing(['seller:id,name', 'categories:id,name,slug', 'tags:id,slug', 'brand:id,name', 'attributeValues.attribute']);

    return [
        'id'            => $this->id,
        'title'         => $this->title,
        'description'   => $this->description,
        'seller_name'   => $this->seller?->name,
        'current_price' => (float) $this->current_price,
        'status'        => $this->status,
        'end_time'      => $this->end_time?->toIso8601String(),
        'condition'     => $this->condition,
        'brand_name'    => $this->brand?->name,
        'category_ids'  => $this->categories->pluck('id')->all(),
        'category_slugs'=> $this->categories->pluck('slug')->all(),
        'tag_slugs'     => $this->tags->pluck('slug')->all(),
        'attributes'    => $this->attributeValues->map(fn ($av) => [
            'slug'  => $av->attribute->slug,
            'value' => $av->value,
        ])->all(),
    ];
}

// Only index active auctions
public function shouldBeSearchable(): bool
{
    return $this->status === self::STATUS_ACTIVE;
}
```

**Update `AuctionController::index()`** — use Scout search:

```php
if ($q = trim((string) $request->input('q', ''))) {
    // Use Elasticsearch
    $auctions = Auction::search($q)
        ->when($categorySlug, fn ($s) => $s->where('category_slugs', $category->slug))
        ->when($minPrice, fn ($s) => $s->where('current_price', '>=', (float) $minPrice))
        ->when($maxPrice, fn ($s) => $s->where('current_price', '<=', (float) $maxPrice))
        ->paginate(12);
} else {
    // Fall back to Eloquent (no search term — browse mode)
    $auctions = $query->paginate(12)->withQueryString();
}
```

**Re-index on auction status change:**

Scout's `Searchable` trait automatically syncs to Elasticsearch when the model is saved (via `saved` event). Since `shouldBeSearchable()` returns `false` for non-active auctions, closed/cancelled auctions are automatically removed from the index.

**Bulk import command (initial indexing):**

```bash
php artisan scout:import "App\Models\Auction"
```

**`config/scout.php`** environment separation:

```php
// .env.local
SCOUT_DRIVER=database  # Use database driver locally — no Elasticsearch needed

// .env.production
SCOUT_DRIVER=elasticsearch
ELASTICSEARCH_HOST=http://elasticsearch:9200
```

#### 3. Infrastructure — `compose.yaml`

```yaml
# Add Elasticsearch service
elasticsearch:
  image: 'docker.elastic.co/elasticsearch/elasticsearch:8.13.0'
  environment:
    - discovery.type=single-node
    - xpack.security.enabled=false
    - ES_JAVA_OPTS=-Xms512m -Xmx512m
  ports:
    - '9200:9200'
  volumes:
    - 'sail-elasticsearch:/usr/share/elasticsearch/data'
  networks:
    - sail
  healthcheck:
    test: ['CMD-SHELL', 'curl -f http://localhost:9200/_cluster/health || exit 1']
    retries: 5
    timeout: 10s

volumes:
  sail-elasticsearch:
    driver: local
```

---

### D. Dependencies & Risks
- **Feature 210 (graceful degradation):** If Elasticsearch is down, fall back to `ILIKE` search. Wrap Scout search in try-catch:

```php
try {
    $results = Auction::search($q)->paginate(12);
} catch (\Throwable $e) {
    Log::warning('Elasticsearch unavailable, falling back to SQL', ['error' => $e->getMessage()]);
    $results = Auction::where('title', 'ilike', "%{$q}%")->active()->paginate(12);
}
```

- **Index lag:** Elasticsearch indexing is async (Scout queues it). Newly published auctions may not appear in search for 1–5 seconds. Acceptable trade-off.
- **Memory:** Single-node Elasticsearch requires ~2 GB RAM. Adjust `ES_JAVA_OPTS` accordingly.

---

### E. Implementation Steps
1. `compose.yaml`: add Elasticsearch service.
2. `composer require laravel/scout babenkoivan/scout-elasticsearch-driver`.
3. Publish Scout config; configure Elasticsearch driver.
4. Create `AuctionIndexConfigurator`.
5. Add `Searchable` trait and `toSearchableArray()` to `Auction` model.
6. Update `AuctionController::index()` and `CategoryBrowseController::show()` with Scout + fallback.
7. Run `php artisan scout:import "App\Models\Auction"`.
8. Add graceful fallback (try-catch) around all Scout calls.
9. Write tests using `Scout::fake()`.

---

### F. Complexity & Priority
- **Complexity:** High
- **Priority:** Scaling

---

## Feature 210 — Graceful Degradation (Redis Down → SQL Fallback)

### A. Feature Overview
When Redis is unavailable, the bidding system automatically switches to the `PessimisticSqlEngine` without manual config changes or redeployment.

**Business goal:** High availability; prevents complete platform outage during Redis failures; reduces on-call incidents.

---

### B. Current State
- `AppServiceProvider` hardcodes `RedisAtomicEngine` as the `BiddingStrategy` binding.
- Switching to `PessimisticSqlEngine` requires editing code and redeploying.
- `config/auction.php` has `engine` key (`redis`/`sql`) but it's not wired to the service provider.

---

### C. Required Changes

#### 1. Database
No changes.

#### 2. Backend Logic

**`app/Providers/AppServiceProvider.php`** — make engine selection dynamic with Redis health check:

```php
public function register(): void
{
    Stripe::setApiKey(config('services.stripe.secret'));

    $this->app->bind(BiddingStrategy::class, function () {
        $configured = config('auction.engine', 'redis');

        if ($configured === 'sql') {
            return app(PessimisticSqlEngine::class);
        }

        // Redis engine selected — verify Redis is reachable
        if ($this->isRedisAvailable()) {
            return app(RedisAtomicEngine::class);
        }

        // Auto-fallback to SQL engine
        \Illuminate\Support\Facades\Log::error('AppServiceProvider: Redis unavailable — falling back to PessimisticSqlEngine');

        // Alert operations team
        try {
            \Illuminate\Support\Facades\Notification::route('mail', config('auction.ops_email'))
                ->notify(new \App\Notifications\RedisDownNotification());
        } catch (\Throwable $e) {
            // Swallow — don't let notification failure block the fallback
        }

        return app(PessimisticSqlEngine::class);
    });

    $this->app->singleton(BidRateLimiter::class, function () {
        return new BidRateLimiter(
            maxBids:       (int) config('auction.rate_limit.max_bids', 10),
            windowSeconds: (int) config('auction.rate_limit.window_seconds', 60),
        );
    });

    $this->app->singleton(\App\Services\AttributePricePredictionService::class);
}

private function isRedisAvailable(): bool
{
    try {
        \Illuminate\Support\Facades\Redis::ping();
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}
```

**Problem:** The `BiddingStrategy` binding is resolved per request. A Redis check on every request adds latency. Solution: cache the Redis health status:

```php
private function isRedisAvailable(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        \Illuminate\Support\Facades\Redis::connection()->ping();
        $available = true;
    } catch (\Throwable $e) {
        $available = false;
    }

    return $available;
}
```

The `static` variable caches the result for the lifetime of the PHP process (single request in FPM). In Octane/long-running processes, use `Cache::remember()` with a 10-second TTL instead.

**`BidRateLimiter` fallback** — when Redis is down, the rate limiter (which uses Redis sorted sets) must also fall back:

```php
// app/Services/Bidding/BidRateLimiter.php — wrap Redis calls
public function check(User $user, Auction $auction): void
{
    try {
        $key = "bid_rate:{$user->id}:{$auction->id}";
        // ... existing Redis logic ...
    } catch (\Throwable $e) {
        // Redis down — use DB-based rate limit as fallback
        $this->checkDatabaseRateLimit($user, $auction);
    }
}

private function checkDatabaseRateLimit(User $user, Auction $auction): void
{
    // Use existing BidRateLimit model (it already exists in the codebase)
    $record = \App\Models\BidRateLimit::firstOrNew([
        'user_id'    => $user->id,
        'auction_id' => $auction->id,
    ]);

    if (! $record->exists || $record->isWindowExpired()) {
        $record->resetWindow($this->windowSeconds);
    }

    if ($record->bid_count >= $this->maxBids) {
        throw \App\Exceptions\BidValidationException::rateLimited($this->windowSeconds);
    }
}

public function hit(User $user, Auction $auction): void
{
    try {
        // ... existing Redis logic ...
    } catch (\Throwable $e) {
        // DB fallback
        \App\Models\BidRateLimit::where('user_id', $user->id)
            ->where('auction_id', $auction->id)
            ->increment('bid_count');
    }
}
```

**SyncRedisPrices command** — already exists (`app/Console/Commands/SyncRedisPrices.php`). After Redis recovery, run this to reconcile any price drift:

```bash
sail artisan auction:sync-prices
```

Add documentation/runbook for operations team.

**`app/Notifications/RedisDownNotification.php`** — new ops notification:

```php
// Simple mail notification to ops team when Redis fallback is triggered
// Channels: mail only
// Content: timestamp, Redis error message, note that SQL fallback is active
```

#### 3. Config — `config/auction.php`
The `engine` key already exists — document it clearly:

```php
'engine' => env('AUCTION_ENGINE', 'redis'),
// 'redis' = RedisAtomicEngine (auto-falls back to SQL if Redis unavailable)
// 'sql'   = PessimisticSqlEngine (forced, for maintenance or Redis-unavailable environments)
```

---

### D. Dependencies & Risks
- **Price reconciliation:** If Redis fails mid-auction, the `current_price` in Postgres may be stale (behind Redis). On Redis recovery, run `sail artisan auction:sync-prices` manually. Add to ops runbook.
- **Concurrent bids during fallback:** `PessimisticSqlEngine` uses `lockForUpdate()` — correct but slower. Under high concurrency, this causes locking contention. Acceptable during an outage scenario.
- **`static` caching in AppServiceProvider:** Works correctly in PHP-FPM (one request = one process). In Laravel Octane, replace `static` with a short-TTL Cache entry to avoid the engine being stuck in SQL mode after Redis recovers.

---

### E. Implementation Steps
1. Update `AppServiceProvider::register()` with dynamic engine selection + Redis health check.
2. Update `BidRateLimiter::check()` and `hit()` with try-catch + `BidRateLimit` model fallback.
3. Create `RedisDownNotification`.
4. Document recovery runbook: "On Redis recovery: run `sail artisan auction:sync-prices`, then restart Horizon workers."
5. Write test: mock Redis connection failure, verify `PessimisticSqlEngine` is bound.
6. Write test: `BidRateLimiter` falls back to DB rate limit when Redis throws.

---

### F. Complexity & Priority
- **Complexity:** Medium
- **Priority:** Scaling

---

## Features 211–220 — Test Suite

### A. Feature Overview
Build a comprehensive test suite covering the platform's critical paths: bidding engine, payment flow, auction lifecycle, notifications, API endpoints, and admin actions.

**Business goal:** Catch regressions before production; enable confident refactoring; reduce QA time.

---

### B. Current State
- `tests/Feature/` has: `AuctionSearchTest`, `CategoryAuctionCountTest`, `ExampleTest`, auth tests, `ProfileTest`.
- `tests/Unit/` has: `AuctionPolicyTest`, `ExampleTest`.
- No tests for: bidding engine, payment/escrow, notifications, seller CRUD, admin actions, API endpoints.
- Pest is installed. `RefreshDatabase` is used in all feature tests (correct).

---

### C. Required Changes — Test Suite Plan

#### Directory Structure

```
tests/
├── Feature/
│   ├── Auth/                     # Existing — keep
│   ├── Bidding/
│   │   ├── PlaceBidTest.php
│   │   ├── AutoBidTest.php
│   │   ├── BidRateLimiterTest.php
│   │   └── SnipeExtensionTest.php
│   ├── Auction/
│   │   ├── AuctionLifecycleTest.php
│   │   ├── AuctionCloseTest.php
│   │   ├── BuyItNowTest.php
│   │   └── AuctionCloneTest.php
│   ├── Payment/
│   │   ├── EscrowTest.php
│   │   ├── PaymentCaptureTest.php
│   │   └── RefundTest.php
│   ├── Notifications/
│   │   ├── OutbidNotificationTest.php
│   │   ├── AuctionEndingSoonTest.php
│   │   └── AuctionClosedNotificationTest.php
│   ├── Api/
│   │   ├── V1/
│   │   │   ├── AuctionApiTest.php
│   │   │   ├── BidApiTest.php
│   │   │   └── AuthApiTest.php
│   ├── Admin/
│   │   ├── UserManagementTest.php
│   │   ├── AuctionManagementTest.php
│   │   └── DisputeTest.php
│   ├── Seller/
│   │   ├── AuctionCrudTest.php
│   │   └── SellerApplicationTest.php
│   └── User/
│       ├── WalletTest.php
│       └── WatchlistTest.php
├── Unit/
│   ├── Models/
│   │   ├── AuctionModelTest.php
│   │   ├── BidModelTest.php
│   │   └── UserModelTest.php
│   ├── Services/
│   │   ├── WalletServiceTest.php
│   │   ├── EscrowServiceTest.php
│   │   ├── PaymentServiceTest.php
│   │   ├── BidValidatorTest.php
│   │   └── ExchangeRateServiceTest.php
│   └── AuctionPolicyTest.php    # Existing — keep
└── Pest.php
```

#### Key Test Implementations

**`tests/Feature/Bidding/PlaceBidTest.php`:**

```php
<?php

use App\Models\Auction;
use App\Models\User;
use App\Services\Bidding\PessimisticSqlEngine;
use App\Contracts\BiddingStrategy;

// Use PessimisticSqlEngine for all bidding tests (no Redis required in CI)
beforeEach(function () {
    app()->bind(BiddingStrategy::class, PessimisticSqlEngine::class);
});

test('user can place a valid bid', function () {
    $seller = User::factory()->create(['wallet_balance' => 0]);
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id'         => $seller->id,
        'starting_price'  => 100,
        'current_price'   => 100,
        'min_bid_increment' => 5,
        'status'          => Auction::STATUS_ACTIVE,
        'end_time'        => now()->addHour(),
    ]);

    $response = $this->actingAs($bidder)->postJson(
        route('auctions.bid', $auction),
        ['amount' => 105]
    );

    $response->assertOk()->assertJson(['success' => true]);
    expect($auction->fresh()->current_price)->toBe('105.00');
});

test('bid below minimum is rejected', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100, 'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE, 'end_time' => now()->addHour(),
    ]);

    $response = $this->actingAs($bidder)->postJson(route('auctions.bid', $auction), ['amount' => 104]);

    $response->assertStatus(422)->assertJson(['error' => 'bid_too_low']);
});

test('seller cannot bid on own auction', function () {
    $seller = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100,
        'status' => Auction::STATUS_ACTIVE, 'end_time' => now()->addHour(),
    ]);

    $response = $this->actingAs($seller)->postJson(route('auctions.bid', $auction), ['amount' => 105]);

    $response->assertStatus(403)->assertJson(['error' => 'self_bid']);
});

test('bid is rejected when auction has ended', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 1000]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->subSecond(), // Already ended
    ]);

    $response = $this->actingAs($bidder)->postJson(route('auctions.bid', $auction), ['amount' => 105]);

    $response->assertStatus(422)->assertJson(['error' => 'auction_ended']);
});

test('bid escrow hold is created when bid is placed', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create(['wallet_balance' => 500, 'held_balance' => 0]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100, 'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE, 'end_time' => now()->addHour(),
    ]);

    $this->actingAs($bidder)->postJson(route('auctions.bid', $auction), ['amount' => 105]);

    $bidder->refresh();
    expect($bidder->held_balance)->toBe('105.00');
    expect(\App\Models\EscrowHold::where('user_id', $bidder->id)->where('auction_id', $auction->id)->exists())->toBeTrue();
});

test('outbid user escrow is released when outbid', function () {
    $seller  = User::factory()->create();
    $bidder1 = User::factory()->create(['wallet_balance' => 500]);
    $bidder2 = User::factory()->create(['wallet_balance' => 500]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 100, 'min_bid_increment' => 5,
        'status' => Auction::STATUS_ACTIVE, 'end_time' => now()->addHour(),
    ]);

    $engine = app(BiddingStrategy::class);
    $engine->placeBid($auction, $bidder1, 105, ['ip_address' => '127.0.0.1']);

    // Process the HandleBidPlaced listener synchronously
    \Illuminate\Support\Facades\Event::fake([\App\Events\BidPlaced::class]);
    $engine->placeBid($auction->fresh(), $bidder2, 110, ['ip_address' => '127.0.0.1']);

    // Simulate listener
    app(\App\Listeners\HandleBidPlaced::class)->releaseOutbidEscrow(
        \App\Models\Bid::latest()->first(), $auction->fresh()
    );

    $bidder1->refresh();
    expect($bidder1->held_balance)->toBe('0.00');
});
```

**`tests/Feature/Payment/EscrowTest.php`:**

```php
test('payment is captured from winners escrow on auction close', function () {
    $seller = User::factory()->create(['wallet_balance' => 0]);
    $winner = User::factory()->create(['wallet_balance' => 500]);

    $auction = Auction::factory()->create([
        'user_id' => $seller->id, 'current_price' => 200, 'status' => Auction::STATUS_COMPLETED,
        'winner_id' => $winner->id, 'winning_bid_amount' => 200,
    ]);

    // Create escrow hold
    \App\Models\EscrowHold::create([
        'user_id' => $winner->id, 'auction_id' => $auction->id,
        'amount' => 200, 'status' => 'active',
    ]);

    $winner->update(['held_balance' => 200]);

    app(\App\Services\PaymentService::class)->captureWinnerPayment($auction);

    $winner->refresh();
    $seller->refresh();

    expect($winner->wallet_balance)->toBe('300.00') // 500 - 200
        ->and($winner->held_balance)->toBe('0.00')
        ->and($seller->wallet_balance)->toBeGreaterThan(0);
});
```

**`tests/Feature/Api/V1/AuctionApiTest.php`:**

```php
test('api returns active auctions', function () {
    $seller = User::factory()->create();
    Auction::factory()->count(3)->create(['user_id' => $seller->id, 'status' => 'active', 'end_time' => now()->addHour()]);
    Auction::factory()->count(2)->create(['user_id' => $seller->id, 'status' => 'completed']);

    $response = $this->getJson('/api/v1/auctions');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'title', 'current_price', 'status']], 'links', 'meta']);
});

test('api requires token to place bid', function () {
    $auction = Auction::factory()->create(['status' => 'active', 'end_time' => now()->addHour()]);

    $response = $this->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount' => 100]);

    $response->assertStatus(401);
});

test('api bid respects token abilities', function () {
    $user    = User::factory()->create(['wallet_balance' => 1000]);
    $seller  = User::factory()->create();
    $auction = Auction::factory()->create(['user_id' => $seller->id, 'status' => 'active', 'end_time' => now()->addHour(), 'current_price' => 100, 'min_bid_increment' => 5]);

    // Token without bid ability
    $token = $user->createToken('test', ['auctions:read'])->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount' => 105]);

    $response->assertStatus(403);
});
```

**`tests/Unit/Services/WalletServiceTest.php`:**

```php
test('deposit credits wallet balance', function () {
    $user    = User::factory()->create(['wallet_balance' => 100]);
    $service = app(\App\Services\WalletService::class);

    $tx = $service->deposit($user, 50.00, 'Test deposit');

    $user->refresh();
    expect($user->wallet_balance)->toBe('150.00')
        ->and($tx->type)->toBe('deposit')
        ->and($tx->amount)->toBe('50.00')
        ->and($tx->balance_after)->toBe('150.00');
});

test('withdraw fails when insufficient balance', function () {
    $user    = User::factory()->create(['wallet_balance' => 50, 'held_balance' => 30]);
    $service = app(\App\Services\WalletService::class);

    expect(fn () => $service->withdraw($user, 30.00, 'Test'))
        ->toThrow(\InvalidArgumentException::class);
    // Available balance is 50 - 30 = 20, which is < 30
});

test('hold reduces available balance', function () {
    $user    = User::factory()->create(['wallet_balance' => 200, 'held_balance' => 0]);
    $service = app(\App\Services\WalletService::class);

    $service->hold($user, 100, 'Bid hold');

    $user->refresh();
    expect($user->held_balance)->toBe('100.00')
        ->and($user->availableBalance())->toBe(100.0);
});
```

**Pest.php global helpers:**

```php
// tests/Pest.php — add helper functions

/**
 * Create a verified seller with wallet balance.
 */
function createSeller(array $overrides = []): \App\Models\User
{
    return \App\Models\User::factory()->create(array_merge([
        'role'                       => 'seller',
        'seller_verified_at'         => now(),
        'seller_application_status'  => 'approved',
        'wallet_balance'             => 1000,
    ], $overrides));
}

/**
 * Create an active auction owned by a seller.
 */
function createActiveAuction(\App\Models\User $seller, array $overrides = []): \App\Models\Auction
{
    return \App\Models\Auction::factory()->create(array_merge([
        'user_id'          => $seller->id,
        'status'           => \App\Models\Auction::STATUS_ACTIVE,
        'end_time'         => now()->addHour(),
        'current_price'    => 100.00,
        'starting_price'   => 100.00,
        'min_bid_increment'=> 5.00,
    ], $overrides));
}

/**
 * Bind PessimisticSqlEngine for tests that don't need Redis.
 */
function useSqlBiddingEngine(): void
{
    app()->bind(
        \App\Contracts\BiddingStrategy::class,
        \App\Services\Bidding\PessimisticSqlEngine::class,
    );
}
```

#### CI Configuration — `.github/workflows/tests.yml`

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:18-alpine
        env:
          POSTGRES_DB: testing
          POSTGRES_USER: sail
          POSTGRES_PASSWORD: password
        ports:
          - 5432:5432
        options: --health-cmd pg_isready --health-interval 10s

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo, pdo_pgsql, redis
          coverage: xdebug

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Copy .env
        run: cp .env.example .env.testing

      - name: Generate key
        run: php artisan key:generate --env=testing

      - name: Run migrations
        run: php artisan migrate --env=testing --force
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: sail
          DB_PASSWORD: password
          QUEUE_CONNECTION: sync
          CACHE_STORE: array
          SCOUT_DRIVER: database

      - name: Run tests
        run: php artisan test --parallel --coverage-clover=coverage.xml
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: sail
          DB_PASSWORD: password
          QUEUE_CONNECTION: sync
          CACHE_STORE: array
          SCOUT_DRIVER: database
          AUCTION_ENGINE: sql

      - name: Upload coverage
        uses: codecov/codecov-action@v4
        with:
          file: coverage.xml
```

---

### D. Critical Test Patterns

1. **Always bind `PessimisticSqlEngine` in test `beforeEach`** — Redis is not available in CI. Use `useSqlBiddingEngine()` global helper.

2. **Queue connection = `sync`** — Process jobs synchronously in tests (set `QUEUE_CONNECTION=sync` in `.env.testing`).

3. **Notification faking:**
```php
\Illuminate\Support\Facades\Notification::fake();
// ... trigger action ...
Notification::assertSentTo($user, \App\Notifications\OutbidNotification::class);
```

4. **Event faking (when testing listeners independently):**
```php
\Illuminate\Support\Facades\Event::fake([\App\Events\BidPlaced::class]);
// ... place bid ...
Event::assertDispatched(\App\Events\BidPlaced::class, fn ($e) => $e->amount === 105.0);
```

5. **Scout faking (for Elasticsearch tests):**
```php
\Laravel\Scout\Scout::fake();
```

---

### E. Implementation Steps
1. Create directory structure under `tests/Feature/` and `tests/Unit/`.
2. Add global helper functions to `tests/Pest.php`.
3. Write bidding tests (PlaceBidTest, SnipeExtensionTest, AutoBidTest, BidRateLimiterTest).
4. Write payment/escrow tests (EscrowTest, PaymentCaptureTest, RefundTest).
5. Write auction lifecycle tests (AuctionLifecycleTest, AuctionCloseTest).
6. Write notification tests.
7. Write API V1 tests.
8. Write admin action tests.
9. Write seller CRUD tests.
10. Create `.github/workflows/tests.yml` CI pipeline.
11. Enforce minimum 80% coverage on `app/Services/` and `app/Models/`.

---

### F. Complexity & Priority
- **Complexity:** High (volume of tests)
- **Priority:** MVP — tests should be written alongside each feature, not after

---

## Feature 206 — Database Read Replicas

### A. Feature Overview
Route read-only database queries to one or more read replicas, reducing load on the primary write node.

**Business goal:** Horizontal scaling for read-heavy workloads (auction browsing, search, analytics).

---

### B. Current State
- Single PostgreSQL node configured in `config/database.php`.
- No `sticky`, `read`, or `write` connection split.

---

### C. Required Changes

#### 1. `config/database.php` — enable Laravel's built-in read/write split:

```php
'pgsql' => [
    'driver'  => 'pgsql',
    'read'    => [
        'host' => [
            env('DB_READ_HOST_1', env('DB_HOST', '127.0.0.1')),
            env('DB_READ_HOST_2', env('DB_HOST', '127.0.0.1')), // Optional second replica
        ],
    ],
    'write' => [
        'host' => env('DB_HOST', '127.0.0.1'),
    ],
    'sticky'   => true,   // Read own writes from primary within same request
    'port'     => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset'  => 'utf8',
    'prefix'   => '',
    'schema'   => 'public',
    'sslmode'  => 'prefer',
],
```

#### 2. `.env` additions:

```env
DB_READ_HOST_1=replica-1.internal
DB_READ_HOST_2=replica-2.internal
```

#### 3. Force primary for critical writes

For transactions that must immediately read their own writes, use `::onWriteConnection()`:

```php
// In BidValidator::ensureBidHighEnough() — reads current_price post-bid
// Ensure we read from primary to avoid replica lag
Auction::onWriteConnection()->find($auction->id);
```

Or use `DB::connection('pgsql')->statement(...)` for explicit primary routing.

#### 4. Replication setup (infrastructure — outside Laravel)
- Use PostgreSQL streaming replication (built-in, synchronous or asynchronous).
- Or managed service: AWS RDS Multi-AZ, Google Cloud SQL with read replicas.
- Replicas are read-only PostgreSQL standby nodes following the primary's WAL stream.

---

### D. Dependencies & Risks
- **`sticky = true`:** Ensures reads within the same request that had a write go to the primary. Critical for bid placement → auction price read consistency.
- **Replication lag:** Async replication introduces 0–100ms lag. Analytics queries can tolerate this; bid validation cannot. Use `onWriteConnection()` for all bid-critical reads.

---

### E. Implementation Steps
1. Update `config/database.php` with read/write split.
2. Add `DB_READ_HOST_1` / `DB_READ_HOST_2` to `.env.example`.
3. Audit bid-critical code paths and add `onWriteConnection()` where replica lag would cause issues.
4. Provision PostgreSQL replica (managed service or self-hosted).
5. Test: verify write queries go to primary, read queries go to replica.

---

### F. Complexity & Priority
- **Complexity:** Low (Laravel config) + Medium (infrastructure provisioning)
- **Priority:** Scaling

---

## Feature 208 — Horizontal Scaling Documentation

### A. Feature Overview
Create an operations runbook documenting how to scale the platform horizontally: multiple app servers, Redis cluster, Horizon workers, Reverb scaling, Elasticsearch cluster.

---

### B. Runbook — Key Scaling Points

**App Servers (PHP-FPM / Nginx)**
- Stateless — any number of instances can run behind a load balancer.
- Sessions stored in database (`SESSION_DRIVER=database`) — shared across instances.
- Cache stored in Redis (`CACHE_STORE=redis`) — shared across instances.

**Redis**
- Single Redis node → Redis Cluster (6 nodes minimum: 3 primary + 3 replica).
- Update `config/database.php` Redis cluster option: `'cluster' => 'redis'`.
- `REDIS_CLUSTER=redis` in `.env`.
- Horizon uses Redis for queue — compatible with Redis Cluster.

**Horizon Workers**
- Scale by increasing `maxProcesses` in `config/horizon.php` per environment.
- Run multiple Horizon supervisor processes on separate servers.
- Queue priorities: `bids` queue gets most workers; `notifications` and `analytics` get fewer.

```php
// config/horizon.php — production
'environments' => [
    'production' => [
        'supervisor-bids' => [
            'connection' => 'redis',
            'queue'      => ['bids'],
            'balance'    => 'auto',
            'maxProcesses'     => 20,
            'balanceMaxShift'  => 3,
            'balanceCooldown'  => 2,
        ],
        'supervisor-notifications' => [
            'queue'      => ['notifications', 'default'],
            'maxProcesses' => 10,
        ],
        'supervisor-analytics' => [
            'queue'      => ['analytics'],
            'maxProcesses' => 3,
        ],
    ],
],
```

**Reverb WebSockets**
- Single node → multiple Reverb nodes with Redis pub/sub for cross-node message routing.
- Enable in `config/reverb.php`:

```php
'scaling' => [
    'enabled' => env('REVERB_SCALING_ENABLED', true),
    'channel' => 'reverb',
    'server'  => [
        'host' => env('REDIS_HOST'),
        'port' => env('REDIS_PORT'),
    ],
],
```

- Put Reverb nodes behind a TCP load balancer (not HTTP — WebSocket connections are long-lived).

**Scheduler**
- Only one server should run the scheduler to avoid duplicate job dispatches.
- Use `withoutOverlapping()` on all scheduled commands (already done in `routes/console.php`).
- For multi-server: use `onOneServer()` in schedule definitions:

```php
Schedule::job(new CloseExpiredAuctions)->everyMinute()->onOneServer()->withoutOverlapping();
```

**Elasticsearch** — see Feature 203 for cluster config.

**PostgreSQL** — see Feature 206 for read replicas.

---

### C. Documentation Output

Create `docs/scaling.md` in the repository:

```markdown
# Horizontal Scaling Guide

## Prerequisites
- All sessions in database (`SESSION_DRIVER=database`) ✅
- Cache in Redis (`CACHE_STORE=redis`) ✅  
- Queues via Horizon + Redis ✅
- No file-based state between requests ✅

## Scaling Checklist
- [ ] Set `REVERB_SCALING_ENABLED=true` + configure Redis for pub/sub
- [ ] Use Redis Cluster for >1000 concurrent bidders
- [ ] Add read replica + configure `DB_READ_HOST_1`
- [ ] Increase Horizon `maxProcesses` per environment tier
- [ ] Add `->onOneServer()` to all scheduled commands
- [ ] Configure Elasticsearch cluster (>500k auctions)
- [ ] Use CDN for media files (move Spatie MediaLibrary disk to S3)

## Critical `->onOneServer()` additions
php artisan schedule:list  # Verify no duplicate jobs in multi-server setup
```

---

### D. Priority
- **Complexity:** Low (configuration + documentation)
- **Priority:** Scaling

---

## Global Shared Components & Architectural Summary

### Components Reused Across All Six Documents

| Component | Features |
|-----------|----------|
| `WalletService` | BIN (7), Listing Fee (67), Tax Documents (73), Referral (43), Credits (50), Currency (120), Payout (124) |
| `EscrowService` | BIN (7), Bid Retraction (48), Vacation Mode (80), Payout (124) |
| `AuditLog::record()` | Every admin write operation across all features |
| Scheduler (`routes/console.php`) | 12+ scheduled tasks across all features |
| `BidValidator` | Vacation Mode (80), BIN validation |
| `AuctionClosed` event | BIN (7), Re-listing (9), Webhooks (195) |
| Spatie MediaLibrary | Auth Cert (90), Featured Banner (97), Lot Items (14) |
| `CategoryService::invalidateCache()` | Category Commission (96), Featured (97) |
| `PessimisticSqlEngine` | Testing (211–220), Redis Degradation (210) |
| Database notifications | 15+ notification types across user, seller, and admin features |

### Priority Matrix

| Priority | Features |
|----------|----------|
| **MVP** (ship first) | Reserve Price Reveal (8), Auction Preview (22), Auto-Save (21), Return Policy (74), Account Deactivation (58), GDPR Export (59), Test Suite (211–220) |
| **Growth** (Q2–Q3) | BIN (7), Re-listing (9), Listing Fee (67), Tax Docs (73), Vacation Mode (80), Referral (43), Follow Seller (45), User Block (46), Thresholds (55), Auth Cert (90), Comparison (92), Category Commission (96), Featured Categories (97), Public API (193), API Docs (194), Webhooks (195), Calendar (200), Currency (120), Support Chat (138), Analytics (169–172) |
| **Scaling** (Q4+) | Lot Auctions (14), Bid Retraction (48), Credits (50), Locale (56), Payout Schedule (124), Maintenance Mode (162), Elasticsearch (203), Read Replicas (206), Graceful Degradation (210), Scaling Docs (208) |

### Top 5 Architectural Improvements

1. **`AuctionResource` JSON layer** (already noted in Feature 8): Single serialisation point for all auction data — eliminates 6+ places that manually build auction arrays.

2. **`SellerSettingsService`**: Centralise all seller profile/preferences persistence (vacation mode, return policy, payout schedule, storefront) into one service rather than ad-hoc controller updates.

3. **Analytics Pipeline → PostgreSQL Materialised Views**: The nightly `GenerateAnalyticsSnapshot` job should eventually become PostgreSQL materialised views refreshed with `CONCURRENTLY` — eliminates data duplication and keeps analytics consistent with source data.

4. **Event-Listener Map documentation**: The platform has 6 events and 3 listeners; webhook support (Feature 195) adds a 4th listener on 3 events. Create a `docs/events.md` listing all events, their listeners, queues, and downstream effects.

5. **Config consolidation**: `config/auction.php` currently mixes percentage-expressed rates (5.0 = 5%) with decimal conventions elsewhere. Before implementing Feature 96 (Category Commission), standardise all rates as decimals (0.05 = 5%) throughout config and models, and add a comment block in `config/auction.php` documenting the convention.