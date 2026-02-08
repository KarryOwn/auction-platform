<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\Admin\DashboardController;
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

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard & Live Monitoring
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/metrics/live', [DashboardController::class, 'liveMetrics'])->name('metrics.live');
    Route::get('/metrics/throughput', [DashboardController::class, 'bidThroughput'])->name('metrics.throughput');
});

require __DIR__.'/auth.php';
