<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\BidController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuctionController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->name('auctions.show');
    Route::post('/auctions/{auction}/bid', [BidController::class, 'store'])->name('auctions.bid');
});

require __DIR__.'/auth.php';
