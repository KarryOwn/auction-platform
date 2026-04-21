<?php

use App\Models\Auction;
use App\Models\User;
use App\Services\Bidding\BidValidator;
use App\Services\VacationModeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BidValidationException;

test('activating vacation mode pauses active auctions', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'end_time' => now()->addDays(2),
    ]);

    $service = app(VacationModeService::class);
    $service->activate($seller, null, 'Going to the beach');

    $seller->refresh();
    $auction->refresh();

    expect($seller->vacation_mode)->toBeTrue();
    expect($seller->vacation_mode_message)->toBe('Going to the beach');

    expect($auction->paused_by_vacation)->toBeTrue();
    expect($auction->original_end_time)->not->toBeNull();
    expect($auction->end_time->isFuture())->toBeTrue(); // Pushed forward 1 year
});

test('deactivating vacation mode resumes active auctions', function () {
    $seller = User::factory()->create(['role' => 'seller', 'vacation_mode' => true]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'paused_by_vacation' => true,
        'paused_at' => now()->subDays(1),
        'original_end_time' => now()->addDays(2),
        'end_time' => now()->addYear(),
    ]);

    $service = app(VacationModeService::class);
    $service->deactivate($seller);

    $seller->refresh();
    $auction->refresh();

    expect($seller->vacation_mode)->toBeFalse();

    expect($auction->paused_by_vacation)->toBeFalse();
    expect($auction->original_end_time)->toBeNull();
    // End time should be original + 1 day
    expect($auction->end_time->diffInHours(now()->addDays(3)))->toBeLessThan(1);
});

test('auto deactivate expired vacation mode', function () {
    $seller = User::factory()->create([
        'role' => 'seller',
        'vacation_mode' => true,
        'vacation_mode_ends_at' => now()->subMinute(),
    ]);

    $service = app(VacationModeService::class);
    $service->autoDeactivateExpired();

    expect($seller->fresh()->vacation_mode)->toBeFalse();
});

test('bidding on paused auction throws exception', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    $buyer = User::factory()->create();
    
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'paused_by_vacation' => true,
    ]);

    $validator = app(BidValidator::class);

    $this->expectException(BidValidationException::class);
    $this->expectExceptionMessage('This auction is temporarily paused while the seller is on vacation.');

    $validator->validate($auction, $buyer, 100);
});
