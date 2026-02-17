<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuctionManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Seller\AnalyticsController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\InsightController;
use App\Http\Controllers\Seller\MessageController as SellerMessageController;
use App\Http\Controllers\Seller\RevenueController;
use App\Http\Controllers\Seller\SellerApplicationController;
use App\Http\Controllers\Seller\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuctionController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->middleware('track.auction.view')->name('auctions.show');
    Route::post('/auctions/{auction}/bid', [BidController::class, 'store'])->name('auctions.bid');
    Route::post('/auctions/{auction}/watch', [AuctionController::class, 'toggleWatch'])->name('auctions.watch');
    Route::post('/auctions/{auction}/auto-bid', [AuctionController::class, 'setAutoBid'])->name('auctions.auto-bid');
    Route::delete('/auctions/{auction}/auto-bid', [AuctionController::class, 'cancelAutoBid'])->name('auctions.cancel-auto-bid');

    Route::get('/seller/apply', [SellerApplicationController::class, 'showForm'])->name('seller.apply.form');
    Route::post('/seller/apply', [SellerApplicationController::class, 'apply'])->name('seller.apply.submit');
    Route::get('/seller/application-status', [SellerApplicationController::class, 'status'])->name('seller.application.status');

    Route::post('/auctions/{auction}/message', [ConversationController::class, 'start'])->name('conversations.start');
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/{conversation}/read', [MessageController::class, 'markRead'])->name('messages.read');
});

Route::prefix('seller')->name('seller.')->middleware(['auth', 'seller'])->group(function () {
    Route::get('/dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/metrics/live', [SellerDashboardController::class, 'liveMetrics'])->name('metrics.live');

    Route::get('/storefront/edit', [StorefrontController::class, 'edit'])->name('storefront.edit');
    Route::patch('/storefront', [StorefrontController::class, 'update'])->name('storefront.update');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/views', [AnalyticsController::class, 'viewData'])->name('analytics.views');
    Route::get('/analytics/bids', [AnalyticsController::class, 'bidData'])->name('analytics.bids');
    Route::get('/analytics/conversion', [AnalyticsController::class, 'conversionData'])->name('analytics.conversion');

    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    Route::get('/revenue/export', [RevenueController::class, 'export'])->name('revenue.export');

    Route::get('/messages', [SellerMessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{conversation}', [SellerMessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [SellerMessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/{conversation}/read', [SellerMessageController::class, 'markRead'])->name('messages.read');

    Route::post('/insights/price-suggestion', [InsightController::class, 'suggestPrice'])->name('insights.price-suggestion');
    Route::get('/auctions/{auction}/insights', [InsightController::class, 'auctionInsights'])->name('auctions.insights');
});

Route::get('/sellers/{slug}', [StorefrontController::class, 'show'])->name('storefront.show');

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

    // Seller Applications
    Route::get('/seller-applications', [AdminSellerApplicationController::class, 'index'])->name('seller-applications.index');
    Route::get('/seller-applications/{application}', [AdminSellerApplicationController::class, 'show'])->name('seller-applications.show');
    Route::post('/seller-applications/{application}/approve', [AdminSellerApplicationController::class, 'approve'])->name('seller-applications.approve');
    Route::post('/seller-applications/{application}/reject', [AdminSellerApplicationController::class, 'reject'])->name('seller-applications.reject');
});

require __DIR__.'/auth.php';
