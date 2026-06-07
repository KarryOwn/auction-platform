<?php

namespace App\Console\Commands;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Services\Bidding\PendingRedisBidStore;
use App\Services\Bidding\PessimisticSqlEngine;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class StressTest extends Command
{
    private const DRIVERS = ['engine', 'http'];

    private const ENGINE_MODES = ['auto', 'redis', 'sql'];

    private const SCENARIOS = [
        'single-hot',
        'multi-even',
        'multi-skewed',
        'redis-failover',
        'redis-midfailover',
        'redis-recovery',
        'queue-backlog',
        'snipe-window',
        'rate-limit',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stress:test
        {auction_id? : Existing auction ID for the legacy single-auction benchmark}
        {count? : Number of bids for the legacy single-auction benchmark}
        {--driver=engine : Benchmark driver: engine or http}
        {--engine=auto : Engine mode: auto, redis, or sql}
        {--scenario=single-hot : Scenario: single-hot, multi-even, multi-skewed, redis-failover, redis-midfailover, redis-recovery, queue-backlog, snipe-window, rate-limit}
        {--auctions=1 : Number of generated benchmark auctions when no auction_id or --auction-ids are supplied}
        {--auction-ids=* : Existing auction IDs to benchmark instead of generating new auctions}
        {--bids-per-auction=100 : Number of generated bid attempts per auction}
        {--concurrency=25 : Max concurrent HTTP requests per batch; engine driver remains in-process}
        {--base-url= : Base URL for the HTTP driver}
        {--drain-queues : Run queue workers until empty after the benchmark before final persistence metrics}
        {--json : Output a machine-readable JSON report only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark auction bidding across single-auction, multi-auction, Redis degradation, queue, snipe, and rate-limit scenarios';

    public function handle(): int
    {
        try {
            $driver = $this->validatedChoice('driver', self::DRIVERS);
            $scenario = $this->validatedChoice('scenario', self::SCENARIOS);
            $requestedEngineMode = $this->validatedChoice('engine', self::ENGINE_MODES);
            $json = (bool) $this->option('json');
            $legacyAuctionId = $this->argument('auction_id');
            $legacyCount = $this->argument('count');
            $redisAvailableBefore = $this->redisAvailable();
            $engineMode = $this->effectiveEngineMode($requestedEngineMode, $scenario, $redisAvailableBefore);

            if ($legacyAuctionId !== null && $legacyCount === null) {
                throw new InvalidArgumentException('The legacy auction_id argument requires a count argument.');
            }

            $this->configureEngine($engineMode);

            $auctions = $this->prepareAuctions($legacyAuctionId, $scenario);
            $botIds = $this->stressBotIds();
            $totalAttempts = $this->totalAttempts($legacyCount, $auctions->count());
            $concurrency = max(1, (int) $this->option('concurrency'));
            $effectiveConcurrency = $driver === 'http' ? $concurrency : 1;
            $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url', 'http://localhost')), '/');
            $engineClass = get_class(app(BiddingStrategy::class));

            if (! $json) {
                $this->line("Launching {$scenario} benchmark with {$totalAttempts} bid attempts across {$auctions->count()} auction(s).");
                $this->line('Driver: '.$driver);
                $this->line('Requested engine: '.$requestedEngineMode);
                $this->line('Effective engine config: '.$engineMode);
                $this->line('Resolved engine: '.class_basename($engineClass));

                if ($driver === 'engine' && $concurrency > 1) {
                    $this->warn('The engine driver runs in-process; use --driver=http to exercise real request concurrency.');
                }

                foreach ($this->scenarioNotes($scenario, $requestedEngineMode, $engineMode, $redisAvailableBefore) as $note) {
                    $this->warn($note);
                }
            }

            $this->syncRedisPrices($auctions, $redisAvailableBefore, $json);

            $attempts = $this->buildAttempts($auctions, $botIds, $totalAttempts, $scenario);
            $auctionIds = $auctions->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $initialBidCounts = $this->bidCounts($auctionIds);
            $queueBefore = $this->queueDepths();
            $failedJobsBefore = $this->failedJobCount();
            $pendingRedisBefore = $this->pendingRedisBidCounts($auctionIds);
            $startedAt = microtime(true);

            if ($scenario === 'redis-midfailover') {
                [$summary, $phaseReports] = $this->runMidFailoverBenchmark($attempts, $driver, $concurrency, $baseUrl);
            } else {
                $summary = $driver === 'http'
                    ? $this->runHttpBenchmark($attempts, $concurrency, $baseUrl, $engineMode)
                    : $this->runEngineBenchmark($attempts);
                $phaseReports = [];
            }

            $duration = max(microtime(true) - $startedAt, 0.000001);
            $queueAfterRun = $this->queueDepths();
            $failedJobsAfterRun = $this->failedJobCount();
            $queueDrain = $this->option('drain-queues')
                ? $this->drainQueues()
                : ['enabled' => false];
            $queueAfterDrain = $this->queueDepths();
            $failedJobsAfterDrain = $this->failedJobCount();
            $finalBidCounts = $this->bidCounts($auctionIds);
            $pendingRedisAfter = $this->pendingRedisBidCounts($auctionIds);
            $redisAvailableAfter = $this->redisAvailable();

            $report = $this->buildReport(
                auctions: $auctions,
                attempts: $attempts,
                summary: $summary,
                duration: $duration,
                driver: $driver,
                requestedEngineMode: $requestedEngineMode,
                effectiveEngineMode: $engineMode,
                engineClass: $engineClass,
                scenario: $scenario,
                baseUrl: $baseUrl,
                concurrency: $concurrency,
                effectiveConcurrency: $effectiveConcurrency,
                redisAvailableBefore: $redisAvailableBefore,
                redisAvailableAfter: $redisAvailableAfter,
                initialBidCounts: $initialBidCounts,
                finalBidCounts: $finalBidCounts,
                queueBefore: $queueBefore,
                queueAfterRun: $queueAfterRun,
                queueAfterDrain: $queueAfterDrain,
                failedJobsBefore: $failedJobsBefore,
                failedJobsAfterRun: $failedJobsAfterRun,
                failedJobsAfterDrain: $failedJobsAfterDrain,
                pendingRedisBefore: $pendingRedisBefore,
                pendingRedisAfter: $pendingRedisAfter,
                queueDrain: $queueDrain,
                scenarioNotes: $this->scenarioNotes($scenario, $requestedEngineMode, $engineMode, $redisAvailableBefore),
                phaseReports: $phaseReports,
            );

            if ($json) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->renderReport($report);
            }

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function validatedChoice(string $option, array $allowed): string
    {
        $value = strtolower((string) $this->option($option));

        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("Invalid --{$option} value. Use one of: ".implode(', ', $allowed).'.');
        }

        return $value;
    }

    private function effectiveEngineMode(string $requested, string $scenario, bool $redisAvailable): string
    {
        if ($requested !== 'auto') {
            return $requested;
        }

        return match ($scenario) {
            'redis-failover' => $redisAvailable ? 'sql' : 'redis',
            'redis-midfailover', 'redis-recovery', 'queue-backlog' => 'redis',
            default => 'auto',
        };
    }

    private function configureEngine(string $engineMode): void
    {
        if ($engineMode === 'redis' || $engineMode === 'sql') {
            config(['auction.engine' => $engineMode]);
        }

        app()->forgetInstance(BiddingStrategy::class);
    }

    private function redisAvailable(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return Collection<int, Auction>
     */
    private function prepareAuctions(int|string|null $legacyAuctionId, string $scenario): Collection
    {
        if ($legacyAuctionId !== null) {
            $auction = Auction::find((int) $legacyAuctionId);

            if (! $auction) {
                throw new InvalidArgumentException("Auction #{$legacyAuctionId} was not found.");
            }

            return collect([$auction]);
        }

        $existingIds = $this->existingAuctionIds();

        if ($existingIds !== []) {
            $auctions = Auction::whereIn('id', $existingIds)
                ->orderBy('id')
                ->get();

            if ($auctions->count() !== count($existingIds)) {
                $found = $auctions->pluck('id')->map(fn ($id) => (int) $id)->all();
                $missing = array_values(array_diff($existingIds, $found));

                throw new InvalidArgumentException('Auction(s) not found: '.implode(', ', $missing));
            }

            return $auctions;
        }

        return $this->createBenchmarkAuctions(max(1, (int) $this->option('auctions')), $scenario);
    }

    /**
     * @return array<int, int>
     */
    private function existingAuctionIds(): array
    {
        return collect((array) $this->option('auction-ids'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim($value))
            ->filter(fn ($value) => $value !== '')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Auction>
     */
    private function createBenchmarkAuctions(int $count, string $scenario): Collection
    {
        $seller = User::updateOrCreate(
            ['email' => 'stress-seller@example.test'],
            [
                'name' => 'Stress Seller',
                'password' => Str::password(32),
                'email_verified_at' => now(),
                'role' => User::ROLE_SELLER,
                'is_banned' => false,
                'seller_verified_at' => now(),
                'seller_application_status' => 'approved',
            ],
        );

        $runId = Str::upper(Str::random(8));
        $endTime = $scenario === 'snipe-window'
            ? now()->addSeconds((int) config('auction.snipe.threshold_seconds', 30))
            : now()->addHours(2);

        $auctions = collect();

        for ($index = 1; $index <= $count; $index++) {
            $startingPrice = 10 + $index;

            $auctions->push(Auction::create([
                'user_id' => $seller->id,
                'title' => "Stress Benchmark {$runId} #{$index}",
                'description' => 'Generated by stress:test for isolated benchmark runs.',
                'starting_price' => $startingPrice,
                'current_price' => $startingPrice,
                'min_bid_increment' => 1.00,
                'snipe_threshold_seconds' => (int) config('auction.snipe.threshold_seconds', 30),
                'snipe_extension_seconds' => (int) config('auction.snipe.extension_seconds', 30),
                'max_extensions' => max(10, (int) config('auction.snipe.max_extensions', 10)),
                'extension_count' => 0,
                'currency' => config('auction.currency', 'USD'),
                'start_time' => now()->subMinute(),
                'end_time' => $endTime->copy(),
                'status' => Auction::STATUS_ACTIVE,
                'reserve_met' => false,
                'bid_count' => 0,
                'unique_bidder_count' => 0,
            ]));
        }

        return $auctions;
    }

    /**
     * @return Collection<int, int>
     */
    private function stressBotIds(): Collection
    {
        $botIds = User::where('email', 'like', 'stress-bot-%@example.test')
            ->where('role', User::ROLE_USER)
            ->where('is_banned', false)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($botIds->isEmpty()) {
            throw new InvalidArgumentException('No stress bot accounts found. Run: sail artisan stress:seed-bots 1000 --balance=1000000');
        }

        return $botIds;
    }

    private function totalAttempts(int|string|null $legacyCount, int $auctionCount): int
    {
        $total = $legacyCount !== null
            ? (int) $legacyCount
            : max(1, (int) $this->option('bids-per-auction')) * $auctionCount;

        if ($total < 1) {
            throw new InvalidArgumentException('Bid attempt count must be at least 1.');
        }

        return $total;
    }

    /**
     * @param  Collection<int, Auction>  $auctions
     */
    private function syncRedisPrices(Collection $auctions, bool $redisAvailable, bool $json): void
    {
        if (! $redisAvailable) {
            if (! $json) {
                $this->warn('Redis is not reachable; skipping Redis price warmup.');
            }

            return;
        }

        foreach ($auctions as $auction) {
            try {
                Redis::set("auction:{$auction->id}:price", (string) $auction->current_price);
            } catch (Throwable $e) {
                if (! $json) {
                    $this->warn("Redis price warmup failed for auction #{$auction->id}: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * @param  Collection<int, Auction>  $auctions
     * @param  Collection<int, int>  $botIds
     * @return array<int, array{auction_id:int, user_id:int, amount:float, ordinal:int}>
     */
    private function buildAttempts(Collection $auctions, Collection $botIds, int $totalAttempts, string $scenario): array
    {
        $auctionIds = $auctions->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $auctionsById = $auctions->keyBy('id');
        $perAuctionOrdinal = array_fill_keys($auctionIds, 0);
        $botPoolSize = $scenario === 'rate-limit'
            ? max(1, min($botIds->count(), (int) ceil($botIds->count() * 0.02)))
            : $botIds->count();
        $attempts = [];

        for ($index = 0; $index < $totalAttempts; $index++) {
            $auctionId = $this->selectAuctionId($auctionIds, $index, $scenario);
            $auction = $auctionsById->get($auctionId);
            $ordinal = $perAuctionOrdinal[$auctionId]++;
            $increment = max(0.01, (float) $auction->min_bid_increment);
            $amount = round($auction->minimumNextBid() + ($increment * $ordinal), 2);

            if ($scenario === 'rate-limit' && $index % 5 === 0) {
                $amount = round((float) $auction->current_price, 2);
            }

            $attempts[] = [
                'auction_id' => $auctionId,
                'user_id' => (int) $botIds[$index % $botPoolSize],
                'amount' => $amount,
                'ordinal' => $ordinal,
            ];
        }

        return $attempts;
    }

    /**
     * @param  array<int, int>  $auctionIds
     */
    private function selectAuctionId(array $auctionIds, int $attemptIndex, string $scenario): int
    {
        if (count($auctionIds) === 1) {
            return $auctionIds[0];
        }

        if (in_array($scenario, ['single-hot', 'snipe-window', 'rate-limit'], true)) {
            return $auctionIds[0];
        }

        if ($scenario === 'multi-skewed') {
            $hotCount = max(1, (int) ceil(count($auctionIds) * 0.2));
            $hotAuctionIds = array_slice($auctionIds, 0, $hotCount);
            $coldAuctionIds = array_slice($auctionIds, $hotCount) ?: $hotAuctionIds;

            return $attemptIndex % 10 < 8
                ? $hotAuctionIds[$attemptIndex % count($hotAuctionIds)]
                : $coldAuctionIds[$attemptIndex % count($coldAuctionIds)];
        }

        return $auctionIds[$attemptIndex % count($auctionIds)];
    }

    /**
     * @param  array<int, array{auction_id:int, user_id:int, amount:float, ordinal:int}>  $attempts
     * @return array<string, mixed>
     */
    private function runEngineBenchmark(array $attempts): array
    {
        $engine = app(BiddingStrategy::class);
        $auctions = Auction::whereIn('id', collect($attempts)->pluck('auction_id')->unique()->all())->get()->keyBy('id');
        $users = User::whereIn('id', collect($attempts)->pluck('user_id')->unique()->all())->get()->keyBy('id');
        $summary = $this->emptySummary();

        foreach ($attempts as $attempt) {
            $startedAt = microtime(true);

            try {
                $engine->placeBid($auctions->get($attempt['auction_id']), $users->get($attempt['user_id']), $attempt['amount'], [
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'StressTest/Engine',
                ]);

                $this->recordAttempt($summary, $attempt, true, null, $startedAt);
            } catch (Throwable $e) {
                $this->recordAttempt($summary, $attempt, false, $e->getMessage() ?: $e::class, $startedAt);
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, array{auction_id:int, user_id:int, amount:float, ordinal:int}>  $attempts
     * @return array<string, mixed>
     */
    private function runHttpBenchmark(array $attempts, int $concurrency, string $baseUrl, string $engineMode): array
    {
        $summary = $this->emptySummary();
        $url = "{$baseUrl}/api/stress-test/bid";

        foreach (array_chunk($attempts, max(1, $concurrency)) as $batch) {
            $startedAt = microtime(true);

            try {
                $responses = Http::pool(function (Pool $pool) use ($batch, $engineMode, $url) {
                    $requests = [];

                    foreach ($batch as $index => $attempt) {
                        $requests[$index] = $pool->post($url, [
                            'secret' => 'thesis-2026',
                            'auction_id' => $attempt['auction_id'],
                            'amount' => $attempt['amount'],
                            'user_id' => $attempt['user_id'],
                            'engine' => $engineMode,
                        ]);
                    }

                    return $requests;
                });

                foreach ($batch as $index => $attempt) {
                    $response = $responses[$index] ?? null;

                    if ($response instanceof Response && $response->successful()) {
                        $this->recordAttempt($summary, $attempt, true, null, $startedAt);

                        continue;
                    }

                    $reason = $response instanceof Response
                        ? ($response->json('error') ?? $response->body() ?: 'Unknown HTTP failure')
                        : 'Missing HTTP response';

                    $this->recordAttempt($summary, $attempt, false, (string) $reason, $startedAt);
                }
            } catch (Throwable $e) {
                foreach ($batch as $attempt) {
                    $this->recordAttempt($summary, $attempt, false, $e->getMessage() ?: $e::class, $startedAt);
                }
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, array{auction_id:int, user_id:int, amount:float, ordinal:int}>  $attempts
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function runMidFailoverBenchmark(array $attempts, string $driver, int $concurrency, string $baseUrl): array
    {
        $splitAt = max(1, (int) floor(count($attempts) / 2));
        $redisAttempts = array_slice($attempts, 0, $splitAt);
        $sqlAttempts = array_slice($attempts, $splitAt);
        $phaseReports = [];

        $redisStartedAt = microtime(true);
        $this->configureEngine('redis');
        $redisSummary = $driver === 'http'
            ? $this->runHttpBenchmark($redisAttempts, $concurrency, $baseUrl, 'redis')
            : $this->runEngineBenchmark($redisAttempts);
        $phaseReports[] = $this->phaseReport('redis-before-shutdown', 'redis', $redisAttempts, $redisSummary, microtime(true) - $redisStartedAt);

        $sqlStartedAt = microtime(true);
        $this->configureEngine('sql');
        $sqlSummary = $driver === 'http'
            ? $this->runHttpBenchmark($sqlAttempts, $concurrency, $baseUrl, 'sql')
            : $this->runEngineBenchmark($sqlAttempts);
        $phaseReports[] = $this->phaseReport('sql-after-shutdown', 'sql', $sqlAttempts, $sqlSummary, microtime(true) - $sqlStartedAt);

        return [$this->mergeSummaries($redisSummary, $sqlSummary), $phaseReports];
    }

    /**
     * @param  array<int, array{auction_id:int, user_id:int, amount:float, ordinal:int}>  $attempts
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function phaseReport(string $name, string $engineMode, array $attempts, array $summary, float $duration): array
    {
        $attemptCount = count($attempts);
        $accepted = (int) $summary['success'];

        return [
            'name' => $name,
            'engine' => $engineMode,
            'attempted' => $attemptCount,
            'accepted' => $accepted,
            'failed' => (int) $summary['fails'],
            'duration_seconds' => round(max($duration, 0.000001), 3),
            'attempts_per_second' => round($attemptCount / max($duration, 0.000001), 2),
            'accepted_per_second' => round($accepted / max($duration, 0.000001), 2),
            'latency_ms' => $this->latencyStats($summary['latencies_ms']),
            'failure_reasons' => $summary['failure_reasons'],
        ];
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @return array<string, mixed>
     */
    private function mergeSummaries(array $first, array $second): array
    {
        $merged = $this->emptySummary();
        $merged['success'] = (int) $first['success'] + (int) $second['success'];
        $merged['fails'] = (int) $first['fails'] + (int) $second['fails'];
        $merged['latencies_ms'] = array_merge($first['latencies_ms'], $second['latencies_ms']);

        foreach (['failure_reasons', 'attempts_by_auction', 'accepted_by_auction'] as $key) {
            foreach ([$first[$key], $second[$key]] as $values) {
                foreach ($values as $name => $total) {
                    $merged[$key][$name] = ($merged[$key][$name] ?? 0) + $total;
                }
            }
        }

        foreach ([$first['max_accepted_amount_by_auction'], $second['max_accepted_amount_by_auction']] as $values) {
            foreach ($values as $auctionId => $amount) {
                $merged['max_accepted_amount_by_auction'][$auctionId] = max(
                    (float) ($merged['max_accepted_amount_by_auction'][$auctionId] ?? 0),
                    (float) $amount,
                );
            }
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'success' => 0,
            'fails' => 0,
            'failure_reasons' => [],
            'latencies_ms' => [],
            'attempts_by_auction' => [],
            'accepted_by_auction' => [],
            'max_accepted_amount_by_auction' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array{auction_id:int, user_id:int, amount:float, ordinal:int}  $attempt
     */
    private function recordAttempt(array &$summary, array $attempt, bool $success, ?string $failureReason, float $startedAt): void
    {
        $auctionId = (string) $attempt['auction_id'];
        $summary['attempts_by_auction'][$auctionId] = ($summary['attempts_by_auction'][$auctionId] ?? 0) + 1;
        $summary['latencies_ms'][] = round((microtime(true) - $startedAt) * 1000, 3);

        if ($success) {
            $summary['success']++;
            $summary['accepted_by_auction'][$auctionId] = ($summary['accepted_by_auction'][$auctionId] ?? 0) + 1;
            $summary['max_accepted_amount_by_auction'][$auctionId] = max(
                (float) ($summary['max_accepted_amount_by_auction'][$auctionId] ?? 0),
                $attempt['amount'],
            );

            return;
        }

        $summary['fails']++;
        $reason = $failureReason ?: 'Unknown failure';
        $summary['failure_reasons'][$reason] = ($summary['failure_reasons'][$reason] ?? 0) + 1;
    }

    /**
     * @param  array<int, int>  $auctionIds
     * @return array<string, int>
     */
    private function bidCounts(array $auctionIds): array
    {
        return Bid::whereIn('auction_id', $auctionIds)
            ->select('auction_id', DB::raw('count(*) as total'))
            ->groupBy('auction_id')
            ->pluck('total', 'auction_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function queueDepths(): array
    {
        if (! Schema::hasTable('jobs')) {
            return [];
        }

        return DB::table('jobs')
            ->select('queue', DB::raw('count(*) as total'))
            ->groupBy('queue')
            ->pluck('total', 'queue')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function failedJobCount(): ?int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return null;
        }

        return (int) DB::table('failed_jobs')->count();
    }

    /**
     * @param  array<int, int>  $auctionIds
     * @return array<string, int|null>
     */
    private function pendingRedisBidCounts(array $auctionIds): array
    {
        $counts = [];

        foreach ($auctionIds as $auctionId) {
            try {
                $counts[(string) $auctionId] = (int) Redis::hlen(PendingRedisBidStore::hashKey($auctionId));
            } catch (Throwable) {
                $counts[(string) $auctionId] = null;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function drainQueues(): array
    {
        $startedAt = microtime(true);

        try {
            $exitCode = Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--queue' => 'bids,broadcasts,notifications,default',
                '--tries' => 1,
                '--timeout' => 60,
            ]);

            return [
                'enabled' => true,
                'exit_code' => $exitCode,
                'duration_seconds' => round(microtime(true) - $startedAt, 3),
            ];
        } catch (Throwable $e) {
            return [
                'enabled' => true,
                'failed' => true,
                'error' => $e->getMessage() ?: $e::class,
                'duration_seconds' => round(microtime(true) - $startedAt, 3),
            ];
        }
    }

    /**
     * @param  Collection<int, Auction>  $auctions
     * @param  array<int, array{auction_id:int, user_id:int, amount:float, ordinal:int}>  $attempts
     * @param  array<string, mixed>  $summary
     * @param  array<string, int>  $initialBidCounts
     * @param  array<string, int>  $finalBidCounts
     * @param  array<string, int>  $queueBefore
     * @param  array<string, int>  $queueAfterRun
     * @param  array<string, int>  $queueAfterDrain
     * @param  array<string, int|null>  $pendingRedisBefore
     * @param  array<string, int|null>  $pendingRedisAfter
     * @param  array<string, mixed>  $queueDrain
     * @param  array<int, string>  $scenarioNotes
     * @param  array<int, array<string, mixed>>  $phaseReports
     * @return array<string, mixed>
     */
    private function buildReport(
        Collection $auctions,
        array $attempts,
        array $summary,
        float $duration,
        string $driver,
        string $requestedEngineMode,
        string $effectiveEngineMode,
        string $engineClass,
        string $scenario,
        string $baseUrl,
        int $concurrency,
        int $effectiveConcurrency,
        bool $redisAvailableBefore,
        bool $redisAvailableAfter,
        array $initialBidCounts,
        array $finalBidCounts,
        array $queueBefore,
        array $queueAfterRun,
        array $queueAfterDrain,
        ?int $failedJobsBefore,
        ?int $failedJobsAfterRun,
        ?int $failedJobsAfterDrain,
        array $pendingRedisBefore,
        array $pendingRedisAfter,
        array $queueDrain,
        array $scenarioNotes,
        array $phaseReports = [],
    ): array {
        $auctionPriceRows = $this->auctionPriceRows(
            $auctions->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            $summary,
            $initialBidCounts,
            $finalBidCounts,
        );
        $success = (int) $summary['success'];
        $fails = (int) $summary['fails'];
        $attemptCount = count($attempts);

        return [
            'scenario' => $scenario,
            'driver' => $driver,
            'base_url' => $driver === 'http' ? $baseUrl : null,
            'requested_engine' => $requestedEngineMode,
            'effective_engine_config' => $effectiveEngineMode,
            'resolved_engine' => class_basename($engineClass),
            'engine_degraded' => is_a($engineClass, PessimisticSqlEngine::class, true)
                && ($effectiveEngineMode !== 'sql' || $scenario === 'redis-failover'),
            'redis_available_before' => $redisAvailableBefore,
            'redis_available_after' => $redisAvailableAfter,
            'notes' => $scenarioNotes,
            'concurrency' => [
                'requested' => $concurrency,
                'effective' => $effectiveConcurrency,
            ],
            'totals' => [
                'auctions' => $auctions->count(),
                'attempted' => $attemptCount,
                'accepted' => $success,
                'failed' => $fails,
                'duration_seconds' => round($duration, 3),
                'attempts_per_second' => round($attemptCount / $duration, 2),
                'accepted_per_second' => round($success / $duration, 2),
            ],
            'latency_ms' => $this->latencyStats($summary['latencies_ms']),
            'failure_reasons' => $summary['failure_reasons'],
            'phases' => $phaseReports,
            'queues' => [
                'before' => $queueBefore,
                'after_run' => $queueAfterRun,
                'after_drain' => $queueAfterDrain,
                'drain' => $queueDrain,
                'failed_jobs_before' => $failedJobsBefore,
                'failed_jobs_after_run' => $failedJobsAfterRun,
                'failed_jobs_after_drain' => $failedJobsAfterDrain,
            ],
            'redis_pending_bids' => [
                'before' => $pendingRedisBefore,
                'after' => $pendingRedisAfter,
            ],
            'auctions' => $auctionPriceRows,
            'system' => [
                'php_memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
                'load_average' => $this->loadAverage(),
            ],
        ];
    }

    /**
     * @param  array<int, int>  $auctionIds
     * @param  array<string, mixed>  $summary
     * @param  array<string, int>  $initialBidCounts
     * @param  array<string, int>  $finalBidCounts
     * @return array<int, array<string, mixed>>
     */
    private function auctionPriceRows(array $auctionIds, array $summary, array $initialBidCounts, array $finalBidCounts): array
    {
        return Auction::whereIn('id', $auctionIds)
            ->orderBy('id')
            ->get()
            ->map(function (Auction $auction) use ($summary, $initialBidCounts, $finalBidCounts) {
                $auctionId = (string) $auction->id;
                $expected = isset($summary['max_accepted_amount_by_auction'][$auctionId])
                    ? (float) $summary['max_accepted_amount_by_auction'][$auctionId]
                    : null;
                $dbPrice = (float) $auction->current_price;
                $redisPrice = $this->redisPrice($auction);
                $initialBidCount = (int) ($initialBidCounts[$auctionId] ?? 0);
                $finalBidCount = (int) ($finalBidCounts[$auctionId] ?? 0);

                return [
                    'auction_id' => (int) $auction->id,
                    'attempted' => (int) ($summary['attempts_by_auction'][$auctionId] ?? 0),
                    'accepted' => (int) ($summary['accepted_by_auction'][$auctionId] ?? 0),
                    'persisted_bid_delta' => max(0, $finalBidCount - $initialBidCount),
                    'expected_final_accepted_price' => $expected,
                    'db_current_price' => $dbPrice,
                    'redis_current_price' => $redisPrice,
                    'db_matches_expected' => $expected === null || abs($dbPrice - $expected) < 0.01,
                    'redis_matches_expected' => $expected === null || $redisPrice === null || abs($redisPrice - $expected) < 0.01,
                ];
            })
            ->values()
            ->all();
    }

    private function redisPrice(Auction $auction): ?float
    {
        try {
            $value = Redis::get("auction:{$auction->id}:price");

            return $value === null ? null : (float) $value;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, float>  $latencies
     * @return array<string, float|null>
     */
    private function latencyStats(array $latencies): array
    {
        if ($latencies === []) {
            return [
                'min' => null,
                'p50' => null,
                'p95' => null,
                'p99' => null,
                'max' => null,
            ];
        }

        sort($latencies);

        return [
            'min' => round($latencies[0], 3),
            'p50' => $this->percentile($latencies, 50),
            'p95' => $this->percentile($latencies, 95),
            'p99' => $this->percentile($latencies, 99),
            'max' => round($latencies[array_key_last($latencies)], 3),
        ];
    }

    /**
     * @param  array<int, float>  $values
     */
    private function percentile(array $values, int $percentile): float
    {
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return round($values[$index], 3);
    }

    /**
     * @return array<int, float>|null
     */
    private function loadAverage(): ?array
    {
        if (! function_exists('sys_getloadavg')) {
            return null;
        }

        $load = sys_getloadavg();

        if ($load === false) {
            return null;
        }

        return array_map(fn ($value) => round((float) $value, 2), $load);
    }

    /**
     * @return array<int, string>
     */
    private function scenarioNotes(string $scenario, string $requestedEngineMode, string $effectiveEngineMode, bool $redisAvailableBefore): array
    {
        $notes = [];

        if ($scenario === 'redis-failover') {
            if ($redisAvailableBefore && $requestedEngineMode === 'auto' && $effectiveEngineMode === 'sql') {
                $notes[] = 'Redis was reachable, so this scenario forces SQL mode to benchmark degraded fallback capacity. Stop Redis and use --engine=redis to validate automatic outage detection.';
            } elseif (! $redisAvailableBefore) {
                $notes[] = 'Redis was unreachable before the run; AppServiceProvider should resolve the SQL fallback when --engine=redis is active.';
            }
        }

        if ($scenario === 'redis-midfailover') {
            $notes[] = 'This scenario simulates mid-bidding Redis shutdown by accepting the first half through Redis, then switching the second half to SQL on the same auction set.';
        }

        if ($scenario === 'queue-backlog') {
            $notes[] = 'Queue backlog is measured through job-table depth before and after the run. Use --drain-queues to measure local drain time.';
        }

        if ($scenario === 'redis-recovery') {
            $notes[] = 'This scenario assumes Redis has been restored. Run auction:sync-prices and queue worker restarts separately for full operations recovery.';
        }

        return $notes;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(array $report): void
    {
        $this->newLine();
        $this->info('--- BENCHMARK REPORT ---');
        $this->line('Scenario: '.$report['scenario']);
        $this->line('Driver: '.$report['driver']);
        $this->line('Resolved Engine: '.$report['resolved_engine']);
        $this->line('Redis Available: '.($report['redis_available_after'] ? 'yes' : 'no'));
        $this->line('Auctions: '.$report['totals']['auctions']);
        $this->line('Attempts: '.$report['totals']['attempted']);
        $this->line('Accepted Bids: '.$report['totals']['accepted']);
        $this->error('Failed/Blocked Bids: '.$report['totals']['failed']);
        $this->line('Time Taken: '.number_format((float) $report['totals']['duration_seconds'], 3).' seconds');
        $this->line('Attempts/sec: '.number_format((float) $report['totals']['attempts_per_second'], 2));
        $this->line('Accepted/sec: '.number_format((float) $report['totals']['accepted_per_second'], 2));

        $this->newLine();
        $this->table(
            ['Latency', 'ms'],
            collect($report['latency_ms'])->map(fn ($value, $key) => [$key, $value ?? 'n/a'])->all(),
        );

        if ($report['failure_reasons'] !== []) {
            $this->newLine();
            $this->warn('--- FAILURE SUMMARY ---');

            foreach ($report['failure_reasons'] as $reason => $total) {
                $this->warn("{$total}x {$reason}");
            }
        }

        $this->newLine();
        $this->table(
            ['Queue', 'Before', 'After Run', 'After Drain'],
            $this->queueTableRows($report['queues']['before'], $report['queues']['after_run'], $report['queues']['after_drain']),
        );

        $this->newLine();
        $this->table(
            ['Auction', 'Attempts', 'Accepted', 'Persisted', 'Expected', 'DB Price', 'Redis Price', 'DB OK'],
            collect($report['auctions'])
                ->take(25)
                ->map(fn ($row) => [
                    $row['auction_id'],
                    $row['attempted'],
                    $row['accepted'],
                    $row['persisted_bid_delta'],
                    $row['expected_final_accepted_price'] ?? 'n/a',
                    $row['db_current_price'],
                    $row['redis_current_price'] ?? 'n/a',
                    $row['db_matches_expected'] ? 'yes' : 'no',
                ])
                ->all(),
        );

        if (count($report['auctions']) > 25) {
            $this->line('Auction table truncated to first 25 rows. Use --json for the full report.');
        }
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $afterRun
     * @param  array<string, int>  $afterDrain
     * @return array<int, array<int, int|string>>
     */
    private function queueTableRows(array $before, array $afterRun, array $afterDrain): array
    {
        $queues = collect(array_keys($before + $afterRun + $afterDrain))->unique()->sort()->values();

        if ($queues->isEmpty()) {
            return [['none', 0, 0, 0]];
        }

        return $queues
            ->map(fn ($queue) => [
                $queue,
                $before[$queue] ?? 0,
                $afterRun[$queue] ?? 0,
                $afterDrain[$queue] ?? 0,
            ])
            ->all();
    }
}
