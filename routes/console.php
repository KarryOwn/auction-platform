<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CloseExpiredAuctions;
use App\Jobs\CaptureAuctionSnapshots;
use App\Jobs\CleanupStaleEscrowHolds;

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

