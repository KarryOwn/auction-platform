<?php

use App\Http\Controllers\Api\V1\AuthController as ApiV1AuthController;
use App\Http\Controllers\Api\V1\AuctionController as ApiV1AuctionController;
use App\Http\Controllers\Api\V1\BidController as ApiV1BidController;
use App\Http\Controllers\Api\V1\CategoryController as ApiV1CategoryController;
use App\Http\Controllers\Api\V1\ProfileController as ApiV1ProfileController;
use App\Http\Controllers\Api\V1\WatchController as ApiV1WatchController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Models\Auction;
use App\Contracts\BiddingStrategy;
use App\Services\CategoryService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\WebhookController as ApiV1WebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Category tree (for JS components)
Route::get('/categories/tree', function () {
    return response()->json(app(CategoryService::class)->getTree());
});

// Category attributes (when selecting a category in forms)
Route::get('/categories/{id}/attributes', function (int $id) {
    $category = Category::findOrFail($id);
    return response()->json($category->getAllAttributes());
});

// Tag autocomplete
Route::get('/tags/search', function (Request $request) {
    $query = $request->input('q', '');
    if (strlen($query) < 2) {
        return response()->json([]);
    }
    return response()->json(app(TagService::class)->search($query));
});

// Brand autocomplete
Route::get('/brands/search', function (Request $request) {
    $query = $request->input('q', '');
    if (strlen($query) < 1) {
        return response()->json(Brand::orderBy('name')->limit(20)->get(['id', 'name', 'slug', 'logo_path']));
    }
    return response()->json(
        Brand::where('name', 'ilike', "%{$query}%")
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'logo_path'])
    );
});

Route::post('/stress-test/bid', function (Request $request) {
    // secret key
    if ($request->input('secret') !== 'thesis-2026') {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Pick only isolated stress-test bot users so real/demo accounts are not mutated.
    $user = User::where('email', 'like', 'stress-bot-%@example.test')
        ->where('role', User::ROLE_USER)
        ->where('is_banned', false)
        ->when(
            $request->filled('user_id'),
            fn ($query) => $query->whereKey($request->integer('user_id')),
            fn ($query) => $query->inRandomOrder(),
        )
        ->first();

    if (! $user) {
        return response()->json(['status' => 'failed', 'error' => 'No stress bot user found. Run stress:seed-bots first.'], 422);
    }

    $auction = Auction::find($request->input('auction_id'));

    if (! $auction) {
        return response()->json(['status' => 'failed', 'error' => 'Auction not found.'], 404);
    }

    // Run 
    try {
        $engine = app(BiddingStrategy::class);
        $engine->placeBid($auction, $user, $request->input('amount'));
        
        return response()->json(['status' => 'success']);
    } catch (\Exception $e) {
        // Return 409 Conflict 
        return response()->json(['status' => 'failed', 'error' => $e->getMessage()], 409);
    }
});

Route::get('/exchange-rates', function () {
    return response()->json(
        ExchangeRate::where('base_currency', 'USD')
            ->orderBy('target_currency')
            ->get(['target_currency', 'rate', 'fetched_at'])
    );
})->name('api.exchange-rates');

Route::prefix('v1')->name('api.v1.')->middleware(['throttle:api'])->group(function () {
    Route::post('/auth/token', [ApiV1AuthController::class, 'token'])->name('auth.token');
    Route::delete('/auth/token', [ApiV1AuthController::class, 'revoke'])
        ->middleware('auth:sanctum')
        ->name('auth.revoke');

    Route::get('/auctions', [ApiV1AuctionController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/{auction}', [ApiV1AuctionController::class, 'show'])->name('auctions.show');

    Route::get('/categories', [ApiV1CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category}', [ApiV1CategoryController::class, 'show'])->name('categories.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auctions/{auction}/bids', [ApiV1BidController::class, 'index'])->name('bids.index');
        Route::post('/auctions/{auction}/bids', [ApiV1BidController::class, 'store'])
            ->middleware('throttle:api-bids')
            ->name('bids.store');

        Route::post('/auctions/{auction}/watch', [ApiV1WatchController::class, 'toggle'])->name('watch.toggle');

        Route::get('/me', [ApiV1ProfileController::class, 'show'])->name('profile.show');
        Route::get('/me/bids', [ApiV1ProfileController::class, 'bids'])->name('profile.bids');
        Route::get('/me/wallet', [ApiV1ProfileController::class, 'wallet'])->name('profile.wallet');
        Route::get('/me/notifications', [ApiV1ProfileController::class, 'notifications'])->name('profile.notifications');

        Route::prefix('/webhooks')->name('webhooks.')->group(function () {
            Route::get('/',           [ApiV1WebhookController::class, 'index'])->name('index');
            Route::post('/',          [ApiV1WebhookController::class, 'store'])->name('store');
            Route::delete('/{endpoint}', [ApiV1WebhookController::class, 'destroy'])->name('destroy');
            Route::get('/deliveries', [ApiV1WebhookController::class, 'deliveries'])->name('deliveries');
            Route::post('/deliveries/{delivery}/redeliver', [ApiV1WebhookController::class, 'redeliver'])->name('redeliver');
        });
    });
});
