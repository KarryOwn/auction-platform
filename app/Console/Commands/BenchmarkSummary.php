<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BenchmarkSummary extends Command
{
    protected $signature = 'stress:summary {--path= : Benchmark JSON file or directory}';

    protected $description = 'Summarize benchmark JSON reports from storage/app/benchmarks';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/benchmarks'));
        $files = File::isDirectory($path)
            ? collect(File::files($path))->filter(fn ($file) => $file->getExtension() === 'json')->values()
            : collect([new \SplFileInfo($path)]);

        if ($files->isEmpty()) {
            $this->warn('No benchmark JSON files found.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($files as $file) {
            $report = json_decode((string) File::get($file->getPathname()), true);

            if (! is_array($report)) {
                $this->warn('Skipping invalid JSON: '.$file->getPathname());
                continue;
            }

            $rows[] = [
                basename($file->getPathname()),
                $report['scenario'] ?? 'n/a',
                $report['pipeline'] ?? 'full',
                $report['resolved_engine'] ?? 'n/a',
                $report['totals']['accepted_per_second'] ?? 'n/a',
                $report['latency_ms']['p95'] ?? 'n/a',
                $report['totals']['failed'] ?? 'n/a',
                isset($report['clean_capacity_comparison'])
                    ? ($report['clean_capacity_comparison'] ? 'yes' : 'no')
                    : $this->legacyCleanCapacityComparison($report),
                $report['persistence']['pending_redis_bids_after'] ?? array_sum(array_filter($report['redis_pending_bids']['after'] ?? [])),
                $report['persistence']['db_mismatch_count'] ?? $this->dbMismatchCount($report),
                $report['drain_times']['total_drain_time_seconds']
                    ?? $report['persistence']['drain_time_seconds']
                    ?? 'n/a',
                $report['drain_times']['persistence_drain_time_seconds']
                    ?? $report['persistence']['persistence_drain_time_seconds']
                    ?? 'n/a',
                $report['realtime_fanout']['broadcast_queue_depth_after'] ?? 'n/a',
                $report['drain_times']['queue_drain_time_seconds']
                    ?? $report['realtime_fanout']['drain_time_seconds']
                    ?? 'n/a',
            ];
        }

        $this->table(
            ['File', 'Scenario', 'Pipeline', 'Engine', 'Accepted/sec', 'p95 ms', 'Failures', 'Clean', 'Pending Redis', 'DB Mismatch', 'Total Drain sec', 'Persistence Drain sec', 'Broadcast Q', 'Queue Drain sec'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function dbMismatchCount(array $report): int
    {
        return collect($report['auctions'] ?? [])
            ->filter(fn ($row) => isset($row['db_matches_expected']) && ! $row['db_matches_expected'])
            ->count();
    }

    private function legacyCleanCapacityComparison(array $report): string
    {
        $pendingRedis = $report['persistence']['pending_redis_bids_after'] ?? array_sum(array_filter($report['redis_pending_bids']['after'] ?? []));
        $dbMismatch = $report['persistence']['db_mismatch_count'] ?? $this->dbMismatchCount($report);
        $drainEnabled = (bool) ($report['queues']['drain']['enabled'] ?? false);

        return $drainEnabled && (int) $pendingRedis === 0 && (int) $dbMismatch === 0 ? 'yes' : 'no';
    }
}
