<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuctionManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AuditLogController;
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
    Route::post('/auctions/{auction}/watch', [AuctionController::class, 'toggleWatch'])->name('auctions.watch');
    Route::post('/auctions/{auction}/auto-bid', [AuctionController::class, 'setAutoBid'])->name('auctions.auto-bid');
    Route::delete('/auctions/{auction}/auto-bid', [AuctionController::class, 'cancelAutoBid'])->name('auctions.cancel-auto-bid');
});

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard & Live Monitoring
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/metrics/live', [DashboardController::class, 'liveMetrics'])->name('metrics.live');
    Route::get('/metrics/throughput', [DashboardController::class, 'bidThroughput'])->name('metrics.throughput');

    // Auction Management
    Route::get('/auctions', [AuctionManagementController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/{auction}', [AuctionManagementController::class, 'show'])->name('auctions.show');
    Route::post('/auctions/{auction}/force-cancel', [AuctionManagementController::class, 'forceCancel'])->name('auctions.force-cancel');
    Route::post('/auctions/{auction}/extend', [AuctionManagementController::class, 'extend'])->name('auctions.extend');

    // User Management
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/ban', [UserManagementController::class, 'ban'])->name('users.ban');
    Route::post('/users/{user}/unban', [UserManagementController::class, 'unban'])->name('users.unban');
    Route::patch('/users/{user}/role', [UserManagementController::class, 'changeRole'])->middleware('admin')->name('users.role');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::patch('/reports/{report}', [ReportController::class, 'review'])->name('reports.review');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

require __DIR__.'/auth.php';
