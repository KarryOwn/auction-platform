<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Auction;
use App\Contracts\BiddingStrategy;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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