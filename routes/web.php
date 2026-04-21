<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\AuctionRatingController;
use App\Http\Controllers\AuctionQuestionController;
use App\Http\Controllers\AuctionReportController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\CategoryBrowseController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuctionManagementController;
use App\Http\Controllers\Admin\AttributeController as AdminAttributeController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\TagController as AdminTagController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\User\InvoiceController;
use App\Http\Controllers\Seller\AnalyticsController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\ImportController as SellerImportController;
use App\Http\Controllers\Seller\ScheduleController as SellerScheduleController;
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
use App\Http\Controllers\User\WithdrawalController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\StripeConnectController;
use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

Route::get('/', function () {
    $liveCount = Cache::remember('live_auction_count', 60, fn() => Auction::where('status','active')->count());
    $featuredAuctions = Cache::remember('featured_auctions', 300, fn() => 
        Auction::featured()->with('media')->take(8)->get()
    );
    $endingSoonAuctions = Cache::remember('ending_soon_auctions', 60, fn() => 
        Auction::active()->where('end_time', '<=', now()->addHours(6))->orderBy('end_time')->take(8)->get()
    );
    return view('welcome', compact('liveCount', 'featuredAuctions', 'endingSoonAuctions'));
});

// Stripe Webhook (outside all middleware — no CSRF)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
     ->name('stripe.webhook');

// Public user profiles
Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('users.show');

// Category browsing (public)
Route::get('/categories', [CategoryBrowseController::class, 'index'])->name('categories.index');
Route::get('/categories/{category:slug}', [CategoryBrowseController::class, 'show'])->name('categories.show');

Route::middleware('auth')->group(function () {
    // Auction browsing (formerly /dashboard, now /auctions)
    Route::get('/auctions', [AuctionController::class, 'index'])->name('auctions.index');

    // User Dashboard
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');

    // User sub-pages
    Route::get('/dashboard/bids', [BidHistoryController::class, 'index'])->name('user.bids');
    Route::get('/dashboard/won', [WonAuctionsController::class, 'index'])->name('user.won-auctions');
    
    
    
    
    // Keyword Alerts
    Route::get('/dashboard/keyword-alerts', [\App\Http\Controllers\User\KeywordAlertController::class, 'index'])->name('user.keyword-alerts');
    Route::post('/dashboard/keyword-alerts', [\App\Http\Controllers\User\KeywordAlertController::class, 'store'])->name('user.keyword-alerts.store');
    Route::delete('/dashboard/keyword-alerts/{alert}', [\App\Http\Controllers\User\KeywordAlertController::class, 'destroy'])->name('user.keyword-alerts.destroy');
    Route::patch('/dashboard/keyword-alerts/{alert}/toggle', [\App\Http\Controllers\User\KeywordAlertController::class, 'toggle'])->name('user.keyword-alerts.toggle');

    Route::get('/dashboard/watchlist', [WatchlistController::class, 'index'])->name('user.watchlist');
    Route::get('/dashboard/activity', [App\Http\Controllers\User\ActivityLogController::class, 'index'])->name('user.activity');

    // Saved Searches
    Route::get('/dashboard/saved-searches', [App\Http\Controllers\User\SavedSearchController::class, 'index'])->name('user.saved-searches');
    Route::post('/dashboard/saved-searches', [App\Http\Controllers\User\SavedSearchController::class, 'store'])->name('user.saved-searches.store');
    Route::delete('/dashboard/saved-searches/{savedSearch}', [App\Http\Controllers\User\SavedSearchController::class, 'destroy'])->name('user.saved-searches.destroy');

    // Wallet
    Route::get('/dashboard/wallet', [WalletController::class, 'show'])->name('user.wallet');
    Route::post('/dashboard/wallet/top-up', [WalletController::class, 'topUp'])->name('user.wallet.top-up');
    Route::post('/dashboard/wallet/stripe/checkout', [WalletController::class, 'stripeCheckout'])->name('user.wallet.stripe.checkout');
    Route::get('/dashboard/wallet/stripe/success', [WalletController::class, 'stripeSuccess'])->name('user.wallet.stripe.success');
    Route::get('/dashboard/wallet/stripe/cancel', fn () => redirect()->route('user.wallet')->with('error', 'Payment cancelled.'))->name('user.wallet.stripe.cancel');
    Route::post('/dashboard/wallet/withdraw', [WithdrawalController::class, 'store'])->name('user.wallet.withdraw');
    Route::get('/dashboard/wallet/export', [WalletController::class, 'exportTransactions'])->name('user.wallet.export');

    // Stripe Connect (bank account onboarding)
    Route::get('/dashboard/wallet/connect', [StripeConnectController::class, 'onboard'])->name('wallet.connect.onboard');
    Route::get('/dashboard/wallet/connect/return', [StripeConnectController::class, 'return'])->name('wallet.connect.return');
    Route::get('/dashboard/wallet/connect/dashboard', [StripeConnectController::class, 'dashboard'])->name('wallet.connect.dashboard');

    // Invoices
    Route::get('/dashboard/invoices', [InvoiceController::class, 'index'])->name('user.invoices');
    Route::get('/dashboard/invoices/{invoice}', [InvoiceController::class, 'show'])->name('user.invoices.show');
    Route::get('/dashboard/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('user.invoices.download');

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
    Route::get('/auctions/{auction}/live-state', [AuctionController::class, 'liveState'])->name('auctions.live-state');
    Route::get('/auctions/{auction}/rate', [AuctionRatingController::class, 'create'])->name('auctions.rate');
    Route::post('/auctions/{auction}/rate', [AuctionRatingController::class, 'store'])->name('auctions.rate.store');
    Route::post('/auctions/{auction}/bid', [BidController::class, 'store'])->name('auctions.bid');
    Route::post('/auctions/{auction}/questions', [AuctionQuestionController::class, 'store'])->name('auctions.questions.store');
    Route::post('/auctions/{auction}/report', [AuctionReportController::class, 'store'])->name('auctions.report');
    Route::post('/auctions/{auction}/watch', [AuctionController::class, 'toggleWatch'])->name('auctions.watch');
    Route::post('/auctions/{auction}/buy-it-now', [\App\Http\Controllers\BuyItNowController::class, 'purchase'])->name('auctions.buy-it-now');
    Route::post('/auctions/{auction}/auto-bid', [AuctionController::class, 'setAutoBid'])->name('auctions.auto-bid');
    Route::delete('/auctions/{auction}/auto-bid', [AuctionController::class, 'cancelAutoBid'])->name('auctions.cancel-auto-bid');
    Route::get('/auctions/{auction}/disputes/create', [DisputeController::class, 'create'])->name('disputes.create');
    Route::post('/auctions/{auction}/disputes', [DisputeController::class, 'store'])->name('disputes.store');
    Route::patch('/questions/{question}/answer', [AuctionQuestionController::class, 'answer'])->name('questions.answer');
    Route::delete('/questions/{question}', [AuctionQuestionController::class, 'destroy'])->name('questions.destroy');

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
    Route::get('/revenue/chart-data', [SellerDashboardController::class, 'revenueChartData'])->name('revenue.chart-data');

    Route::get('/storefront/edit', [StorefrontController::class, 'edit'])->name('storefront.edit');
    Route::patch('/storefront', [StorefrontController::class, 'update'])->name('storefront.update');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/views', [AnalyticsController::class, 'viewData'])->name('analytics.views');
    Route::get('/analytics/bids', [AnalyticsController::class, 'bidData'])->name('analytics.bids');
    Route::get('/analytics/conversion', [AnalyticsController::class, 'conversionData'])->name('analytics.conversion');

    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    Route::get('/revenue/export', [RevenueController::class, 'export'])->name('revenue.export');

    Route::prefix('/tax-documents')->name('tax-documents.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Seller\TaxDocumentController::class, 'index'])->name('index');
        Route::post('/generate', [\App\Http\Controllers\Seller\TaxDocumentController::class, 'generate'])->name('generate');
        Route::get('/{document}/download', [\App\Http\Controllers\Seller\TaxDocumentController::class, 'download'])->name('download');
    });

    Route::get('/messages', [SellerMessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{conversation}', [SellerMessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [SellerMessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/{conversation}/read', [SellerMessageController::class, 'markRead'])->name('messages.read');

    Route::post('/insights/price-suggestion', [InsightController::class, 'suggestPrice'])->name('insights.price-suggestion');
    Route::post('/insights/predict', [InsightController::class, 'predict'])->name('insights.predict');
    Route::get('/insights/category-attributes', [InsightController::class, 'categoryAttributes'])->name('insights.category-attributes');
    Route::get('/auctions/{auction}/insights', [InsightController::class, 'auctionInsights'])->name('auctions.insights');

    Route::get('/auctions', [AuctionCrudController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/schedule', [SellerScheduleController::class, 'index'])->name('auctions.schedule');
    Route::get('/auctions/import', [SellerImportController::class, 'create'])->name('auctions.import');
    Route::post('/auctions/import', [SellerImportController::class, 'store'])->name('auctions.import.store');
    Route::get('/auctions/import/template', [SellerImportController::class, 'template'])->name('auctions.import.template');
    Route::get('/auctions/create', [AuctionCrudController::class, 'create'])->name('auctions.create');
    Route::post('/auctions', [AuctionCrudController::class, 'store'])->name('auctions.store');
    Route::get('/auctions/{auction}/edit', [AuctionCrudController::class, 'edit'])->name('auctions.edit');
    Route::patch('/auctions/{auction}', [AuctionCrudController::class, 'update'])->name('auctions.update');
    Route::get('/auctions/{auction}/preview', [AuctionCrudController::class, 'preview'])->name('auctions.preview');
    Route::get('/auctions/{auction}/listing-fee-preview', [AuctionCrudController::class, 'listingFeePreview'])->name('auctions.listing-fee-preview');
    Route::patch('/auctions/{auction}/auto-save', [\App\Http\Controllers\Seller\AuctionDraftController::class, 'autoSave'])
        ->middleware('throttle:30,1')
        ->name('auctions.auto-save');
    Route::post('/auctions/{auction}/clone', [AuctionCrudController::class, 'clone'])->name('auctions.clone');
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
    Route::get('/metrics/fraud-alerts', [DashboardController::class, 'fraudAlerts'])->name('metrics.fraud-alerts');
    Route::get('/metrics/throughput', [DashboardController::class, 'bidThroughput'])->name('metrics.throughput');

    // Auction Management
    Route::get('/auctions', [AuctionManagementController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/{auction}', [AuctionManagementController::class, 'show'])->name('auctions.show');
    Route::post('/auctions/{auction}/feature', [AuctionManagementController::class, 'feature'])->name('auctions.feature');
    Route::delete('/auctions/{auction}/feature', [AuctionManagementController::class, 'unfeature'])->name('auctions.unfeature');
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

    // Disputes
    Route::get('/disputes', [AdminDisputeController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/{dispute}', [AdminDisputeController::class, 'show'])->name('disputes.show');
    Route::patch('/disputes/{dispute}', [AdminDisputeController::class, 'update'])->name('disputes.update');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Payment Management
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/transactions', [AdminPaymentController::class, 'transactions'])->name('payments.transactions');

    // Refunds
    Route::get('/auctions/{auction}/refund', [AdminRefundController::class, 'show'])->name('auctions.refund');
    Route::post('/auctions/{auction}/refund', [AdminRefundController::class, 'process'])->name('auctions.refund.process');

    // Seller Applications
    Route::get('/seller-applications', [AdminSellerApplicationController::class, 'index'])->name('seller-applications.index');
    Route::get('/seller-applications/{application}', [AdminSellerApplicationController::class, 'show'])->name('seller-applications.show');
    Route::post('/seller-applications/{application}/approve', [AdminSellerApplicationController::class, 'approve'])->name('seller-applications.approve');
    Route::post('/seller-applications/{application}/reject', [AdminSellerApplicationController::class, 'reject'])->name('seller-applications.reject');

    // Category Management
    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::patch('/categories/{category}/toggle', [AdminCategoryController::class, 'toggle'])->name('categories.toggle');
    Route::post('/categories/reorder', [AdminCategoryController::class, 'reorder'])->name('categories.reorder');

    // Brand Management
    Route::get('/brands', [AdminBrandController::class, 'index'])->name('brands.index');
    Route::get('/brands/create', [AdminBrandController::class, 'create'])->name('brands.create');
    Route::post('/brands', [AdminBrandController::class, 'store'])->name('brands.store');
    Route::get('/brands/{brand}/edit', [AdminBrandController::class, 'edit'])->name('brands.edit');
    Route::put('/brands/{brand}', [AdminBrandController::class, 'update'])->name('brands.update');
    Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy'])->name('brands.destroy');

    // Tag Management
    Route::get('/tags', [AdminTagController::class, 'index'])->name('tags.index');
    Route::post('/tags', [AdminTagController::class, 'store'])->name('tags.store');
    Route::put('/tags/{tag}', [AdminTagController::class, 'update'])->name('tags.update');
    Route::delete('/tags/{tag}', [AdminTagController::class, 'destroy'])->name('tags.destroy');
    Route::post('/tags/merge', [AdminTagController::class, 'merge'])->name('tags.merge');

    // Attribute Management
    Route::get('/attributes', [AdminAttributeController::class, 'index'])->name('attributes.index');
    Route::get('/attributes/create', [AdminAttributeController::class, 'create'])->name('attributes.create');
    Route::post('/attributes', [AdminAttributeController::class, 'store'])->name('attributes.store');
    Route::get('/attributes/{attribute}/edit', [AdminAttributeController::class, 'edit'])->name('attributes.edit');
    Route::put('/attributes/{attribute}', [AdminAttributeController::class, 'update'])->name('attributes.update');
    Route::delete('/attributes/{attribute}', [AdminAttributeController::class, 'destroy'])->name('attributes.destroy');
});

require __DIR__.'/auth.php';
