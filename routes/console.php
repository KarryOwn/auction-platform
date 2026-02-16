<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CloseExpiredAuctions;
use App\Jobs\CaptureAuctionSnapshots;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
*/

// Close auctions whose end_time has passed — runs every minute.
Schedule::job(new CloseExpiredAuctions)->everyMinute();

// Capture periodic auction snapshots for analytics.
Schedule::job(new CaptureAuctionSnapshots)
    ->everyTwoMinutes()
    ->name('capture-auction-snapshots')
    ->withoutOverlapping();

