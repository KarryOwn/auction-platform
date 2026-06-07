<?php

use App\Events\BidPlaced;
use App\Events\PriceUpdated;
use App\Jobs\BatchPersistRedisBids;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Event::fake([BidPlaced::class, PriceUpdated::class]);
});

function createStressBotsForBenchmark(int $count): void
{
    for ($index = 1; $index <= $count; $index++) {
        User::factory()->create([
            'name' => "Stress Bot {$index}",
            'email' => sprintf('stress-bot-%04d@example.test', $index),
            'role' => User::ROLE_USER,
            'is_banned' => false,
            'wallet_balance' => 1000000,
            'held_balance' => 0,
        ]);
    }
}

test('legacy single auction benchmark signature still works', function () {
    createStressBotsForBenchmark(3);
    $seller = createSeller(['wallet_balance' => 0]);
    $auction = createActiveAuction($seller, [
        'starting_price' => 10,
        'current_price' => 10,
        'min_bid_increment' => 1,
    ]);

    $exitCode = Artisan::call('stress:test', [
        'auction_id' => $auction->id,
        'count' => 3,
        '--driver' => 'engine',
        '--engine' => 'sql',
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Accepted Bids: 3')
        ->and($auction->fresh()->current_price)->toBe('13.00');
});

test('multi auction json scenario reports benchmark metrics', function () {
    createStressBotsForBenchmark(6);

    $exitCode = Artisan::call('stress:test', [
        '--scenario' => 'multi-even',
        '--auctions' => 2,
        '--bids-per-auction' => 2,
        '--driver' => 'engine',
        '--engine' => 'sql',
        '--json' => true,
    ]);

    $report = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($report)->toBeArray()
        ->and($report['scenario'])->toBe('multi-even')
        ->and($report['driver'])->toBe('engine')
        ->and($report['pipeline'])->toBe('full')
        ->and($report['resolved_engine'])->toBe('PessimisticSqlEngine')
        ->and($report['totals']['auctions'])->toBe(2)
        ->and($report['totals']['attempted'])->toBe(4)
        ->and($report['totals']['accepted'])->toBe(4)
        ->and($report['latency_ms'])->toHaveKeys(['min', 'p50', 'p95', 'p99', 'max'])
        ->and($report['queues'])->toHaveKeys(['before', 'after_run', 'after_drain', 'drain'])
        ->and($report['queue_state'])->toHaveKeys(['before', 'after_run', 'after_drain'])
        ->and($report)->toHaveKeys(['accept_only_expected_lag', 'clean_capacity_comparison'])
        ->and($report['redis_pending_bids'])->toHaveKeys(['before', 'after']);

    expect(Auction::where('title', 'like', 'Stress Benchmark%')->count())->toBe(2);
});

test('redis failover scenario benchmarks degraded sql engine mode', function () {
    createStressBotsForBenchmark(2);

    $exitCode = Artisan::call('stress:test', [
        '--scenario' => 'redis-failover',
        '--auctions' => 1,
        '--bids-per-auction' => 2,
        '--driver' => 'engine',
        '--json' => true,
    ]);

    $report = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($report['scenario'])->toBe('redis-failover')
        ->and($report['resolved_engine'])->toBe('PessimisticSqlEngine')
        ->and($report['engine_degraded'])->toBeTrue()
        ->and($report['totals']['attempted'])->toBe(2);
});

test('redis midfailover scenario reports redis and sql phases', function () {
    createStressBotsForBenchmark(4);

    $exitCode = Artisan::call('stress:test', [
        '--scenario' => 'redis-midfailover',
        '--auctions' => 1,
        '--bids-per-auction' => 4,
        '--driver' => 'engine',
        '--json' => true,
    ]);

    $report = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($report['scenario'])->toBe('redis-midfailover')
        ->and($report['totals']['attempted'])->toBe(4)
        ->and($report['phases'])->toHaveCount(2)
        ->and($report['phases'][0]['engine'])->toBe('redis')
        ->and($report['phases'][1]['engine'])->toBe('sql');
});

test('redis midfailover http scenario sends redis then sql engines', function () {
    createStressBotsForBenchmark(2);
    Http::fake([
        'http://localhost/api/stress-test/bid' => Http::response(['status' => 'success']),
    ]);

    $exitCode = Artisan::call('stress:test', [
        '--scenario' => 'redis-midfailover',
        '--auctions' => 1,
        '--bids-per-auction' => 2,
        '--driver' => 'http',
        '--concurrency' => 1,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $engines = [];
    Http::assertSent(function ($request) use (&$engines) {
        $engines[] = $request['engine'];

        return true;
    });

    expect($engines)->toBe(['redis', 'sql']);
});

test('stress test api endpoint can force sql engine for http benchmarks', function () {
    createStressBotsForBenchmark(1);
    $seller = createSeller(['wallet_balance' => 0]);
    $auction = createActiveAuction($seller, [
        'starting_price' => 10,
        'current_price' => 10,
        'min_bid_increment' => 1,
    ]);
    $bot = User::where('email', 'stress-bot-0001@example.test')->firstOrFail();

    $response = $this->postJson('/api/stress-test/bid', [
        'secret' => 'thesis-2026',
        'auction_id' => $auction->id,
        'amount' => 11,
        'user_id' => $bot->id,
        'engine' => 'sql',
    ]);

    $response->assertOk()->assertJson(['status' => 'success']);
    expect($auction->fresh()->current_price)->toBe('11.00');
});

test('http benchmark driver includes selected engine in stress requests', function () {
    createStressBotsForBenchmark(1);
    Http::fake([
        'http://localhost/api/stress-test/bid' => Http::response(['status' => 'success']),
    ]);

    $exitCode = Artisan::call('stress:test', [
        '--scenario' => 'single-hot',
        '--auctions' => 1,
        '--bids-per-auction' => 1,
        '--driver' => 'http',
        '--engine' => 'sql',
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    Http::assertSent(fn ($request) => $request['engine'] === 'sql'
        && $request['pipeline'] === 'full'
        && $request['secret'] === 'thesis-2026');
});

test('http benchmark driver counts responses across multiple batches', function () {
    createStressBotsForBenchmark(3);
    Http::fake([
        'http://localhost/api/stress-test/bid' => Http::response(['status' => 'success']),
    ]);

    $exitCode = Artisan::call('stress:test', [
        '--scenario' => 'single-hot',
        '--auctions' => 1,
        '--bids-per-auction' => 3,
        '--driver' => 'http',
        '--engine' => 'sql',
        '--concurrency' => 1,
        '--json' => true,
    ]);

    $report = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($report['totals']['attempted'])->toBe(3)
        ->and($report['totals']['accepted'])->toBe(3)
        ->and($report['totals']['failed'])->toBe(0);
});

test('accept only redis benchmark suppresses queue and price broadcast dispatch while recording expected lag', function () {
    Queue::fake();
    createStressBotsForBenchmark(2);

    $exitCode = Artisan::call('stress:test', [
        '--scenario' => 'single-hot',
        '--auctions' => 1,
        '--bids-per-auction' => 2,
        '--driver' => 'engine',
        '--engine' => 'redis',
        '--pipeline' => 'accept-only',
        '--json' => true,
    ]);

    $report = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($report['pipeline'])->toBe('accept-only')
        ->and($report['totals']['accepted'])->toBe(2)
        ->and($report['persistence']['pending_redis_bids_after'])->toBe(2)
        ->and($report['accept_only_expected_lag'])->toBe(2);

    Queue::assertNotPushed(BatchPersistRedisBids::class);
    Event::assertNotDispatched(PriceUpdated::class);
});

test('full redis benchmark drains pending bids with no db mismatch', function () {
    createStressBotsForBenchmark(2);

    $exitCode = Artisan::call('stress:test', [
        '--scenario' => 'single-hot',
        '--auctions' => 1,
        '--bids-per-auction' => 2,
        '--driver' => 'engine',
        '--engine' => 'redis',
        '--pipeline' => 'full',
        '--drain-queues' => true,
        '--drain-timeout' => 2,
        '--json' => true,
    ]);

    $report = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($report['pipeline'])->toBe('full')
        ->and($report['totals']['accepted'])->toBe(2)
        ->and($report['persistence']['pending_redis_bids_after'])->toBe(0)
        ->and($report['persistence']['db_mismatch_count'])->toBe(0)
        ->and($report['queues']['drain']['enabled'])->toBeTrue()
        ->and($report['queues']['drain']['timeout_seconds'])->toBe(2)
        ->and($report['queues']['drain']['queue_order'])->toBe(['bids', 'broadcasts', 'notifications', 'default']);
});
