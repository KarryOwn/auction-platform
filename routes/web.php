<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuctionManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Seller\AnalyticsController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\InsightController;
use App\Http\Controllers\Seller\AuctionCrudController;
use App\Http\Controllers\Seller\MessageController as SellerMessageController;
use App\Http\Controllers\Seller\RevenueController;
use App\Http\Controllers\Seller\SellerApplicationController;
use App\Http\Controllers\Seller\StorefrontController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\BidHistoryController;
use App\Http\Controllers\User\WonAuctionsController;
use App\Http\Controllers\User\WatchlistController;
use App\Http\Controllers\User\WalletController;
use App\Http\Controllers\User\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public user profiles
Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('users.show');

Route::middleware('auth')->group(function () {
    // Auction browsing (formerly /dashboard, now /auctions)
    Route::get('/auctions', [AuctionController::class, 'index'])->name('auctions.index');

    // User Dashboard
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');

    // User sub-pages
    Route::get('/dashboard/bids', [BidHistoryController::class, 'index'])->name('user.bids');
    Route::get('/dashboard/won', [WonAuctionsController::class, 'index'])->name('user.won-auctions');
    Route::get('/dashboard/watchlist', [WatchlistController::class, 'index'])->name('user.watchlist');

    // Wallet
    Route::get('/dashboard/wallet', [WalletController::class, 'show'])->name('user.wallet');
    Route::post('/dashboard/wallet/top-up', [WalletController::class, 'topUp'])->name('user.wallet.top-up');
    Route::get('/dashboard/wallet/export', [WalletController::class, 'exportTransactions'])->name('user.wallet.export');

    // Notification Preferences
    Route::get('/dashboard/notifications', [NotificationPreferenceController::class, 'edit'])->name('user.notification-preferences');
    Route::put('/dashboard/notifications', [NotificationPreferenceController::class, 'update'])->name('user.notification-preferences.update');

    // Mark all notifications as read (AJAX)
    Route::post('/notifications/mark-all-read', function (Request $request) {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    })->name('notifications.mark-all-read');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Auction detail & actions
    Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->middleware('track.auction.view')->name('auctions.show');
    Route::post('/auctions/{auction}/bid', [BidController::class, 'store'])->name('auctions.bid');
    Route::post('/auctions/{auction}/watch', [AuctionController::class, 'toggleWatch'])->name('auctions.watch');
    Route::post('/auctions/{auction}/auto-bid', [AuctionController::class, 'setAutoBid'])->name('auctions.auto-bid');
    Route::delete('/auctions/{auction}/auto-bid', [AuctionController::class, 'cancelAutoBid'])->name('auctions.cancel-auto-bid');

    // Seller Application
    Route::get('/seller/apply', [SellerApplicationController::class, 'showForm'])->name('seller.apply.form');
    Route::post('/seller/apply', [SellerApplicationController::class, 'apply'])->name('seller.apply.submit');
    Route::get('/seller/application-status', [SellerApplicationController::class, 'status'])->name('seller.application.status');

    // Messaging
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

    Route::get('/auctions', [AuctionCrudController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/create', [AuctionCrudController::class, 'create'])->name('auctions.create');
    Route::post('/auctions', [AuctionCrudController::class, 'store'])->name('auctions.store');
    Route::get('/auctions/{auction}/edit', [AuctionCrudController::class, 'edit'])->name('auctions.edit');
    Route::patch('/auctions/{auction}', [AuctionCrudController::class, 'update'])->name('auctions.update');
    Route::post('/auctions/{auction}/publish', [AuctionCrudController::class, 'publish'])->name('auctions.publish');
    Route::post('/auctions/{auction}/cancel', [AuctionCrudController::class, 'cancel'])->name('auctions.cancel');
    Route::delete('/auctions/{auction}', [AuctionCrudController::class, 'destroy'])->name('auctions.destroy');

    Route::post('/auctions/{auction}/images', [AuctionCrudController::class, 'uploadImage'])->name('auctions.images.upload');
    Route::delete('/auctions/{auction}/images/{media}', [AuctionCrudController::class, 'deleteImage'])->name('auctions.images.delete');
    Route::post('/auctions/{auction}/images/reorder', [AuctionCrudController::class, 'reorderImages'])->name('auctions.images.reorder');
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
