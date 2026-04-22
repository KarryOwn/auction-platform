<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CreateRandomAuction;
use App\Jobs\CloseExpiredAuctions;
use App\Jobs\CaptureAuctionSnapshots;
use App\Jobs\CleanupStaleEscrowHolds;
use App\Models\Category;
use App\Services\CategoryService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
*/

// Close auctions whose end_time has passed — runs every minute.
Schedule::call(function (): void {
    app()->call([app(CloseExpiredAuctions::class), 'handle']);
})
    ->everyMinute()
    ->name('close-expired-auctions')
    ->withoutOverlapping();

// Capture periodic auction snapshots for analytics.
Schedule::job(new CaptureAuctionSnapshots)
    ->everyTwoMinutes()
    ->name('capture-auction-snapshots')
    ->withoutOverlapping();

// Notify watchers/bidders about auctions ending within 30 minutes.
Schedule::command('auctions:notify-ending-soon')
    ->everyMinute()
    ->name('notify-ending-soon')
    ->withoutOverlapping();

// Cleanup stale escrow holds on completed/cancelled auctions (safety net).
Schedule::job(new CleanupStaleEscrowHolds)
    ->daily()
    ->name('cleanup-stale-escrow-holds')
    ->withoutOverlapping();

// Generate a random active auction for demo activity.
Schedule::job(new CreateRandomAuction)
    ->everyFiveMinutes()
    ->name('create-random-auction')
    ->withoutOverlapping();

// Warm cache keys used on discovery/homepage paths.
Schedule::command('cache:warm --key=featured_auctions')
    ->everyFiveMinutes()
    ->name('warm-featured-auctions-cache')
    ->withoutOverlapping();

Schedule::command('cache:warm --key=featured_categories')
    ->everyFiveMinutes()
    ->name('warm-featured-categories-cache')
    ->withoutOverlapping();

Schedule::command('cache:warm --key=category_tree --key=root_categories')
    ->hourly()
    ->name('warm-category-tree-and-roots-cache')
    ->withoutOverlapping();

// Auto-deactivate expired vacation modes
Schedule::call(fn () => app(\App\Services\VacationModeService::class)->autoDeactivateExpired())
    ->everyFiveMinutes()
    ->name('auto-deactivate-vacation-mode')
    ->withoutOverlapping();

Schedule::call(function () {
    Category::where('is_featured', true)
        ->whereNotNull('featured_until')
        ->where('featured_until', '<=', now())
        ->update(['is_featured' => false]);

    app(CategoryService::class)->invalidateCache();
})->hourly()->name('unfeature-expired-categories');

Schedule::call(function () {
    \App\Models\User::where('is_deactivated', true)
        ->where('reactivation_deadline', '<=', now())
        ->each(fn ($u) => $u->forceDelete());
})->daily()->name('purge-deactivated-accounts');

Schedule::call(function () {
    \App\Models\DataExportRequest::where('status', 'ready')
        ->where('expires_at', '<=', now())
        ->each(function ($export) {
            \Illuminate\Support\Facades\Storage::delete($export->file_path);
            $export->update(['status' => 'expired', 'file_path' => null]);
        });
})->hourly()->name('purge-expired-exports');
