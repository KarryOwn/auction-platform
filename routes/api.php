<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Models\Auction;
use App\Contracts\BiddingStrategy;
use App\Services\CategoryService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    // Pick a Random User 
    $user = User::inRandomOrder()->first();
    $auction = Auction::find($request->input('auction_id'));

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