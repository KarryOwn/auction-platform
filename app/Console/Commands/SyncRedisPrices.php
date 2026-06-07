<?php

namespace App\Console\Commands;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use App\Services\Bidding\BiddingEngineHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * One-off recovery command: syncs Redis prices back to the database
 * for any active auctions where the DB is behind Redis.
 */
class SyncRedisPrices extends Command
{
    protected $signature = 'auction:sync-prices
                            {--dry-run : Show what would be updated without making changes}
                            {--auction= : Sync a specific auction by ID}
                            {--to-redis : Restore Redis prices from database prices and clear degraded state}';

    protected $description = 'Sync auction prices between Redis and the database (recovery tool)';

    public function handle(BiddingStrategy $biddingStrategy, BiddingEngineHealth $health): int
    {
        $query = Auction::where('status', Auction::STATUS_ACTIVE);

        if ($auctionId = $this->option('auction')) {
            $query->where('id', $auctionId);
        }

        $auctions = $query->get();
        $dryRun   = $this->option('dry-run');
        $toRedis  = (bool) $this->option('to-redis');
        $synced   = 0;

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Checking {$auctions->count()} active auction(s)...");

        if ($toRedis) {
            try {
                Redis::connection()->ping();
            } catch (\Throwable $e) {
                $this->error('Redis is still unavailable: '.$e->getMessage());
                $health->markRedisDegraded($e->getMessage());

                return self::FAILURE;
            }
        }

        foreach ($auctions as $auction) {
            $dbPrice    = (float) $auction->current_price;
            $redisPrice = $toRedis ? $this->redisPrice($auction) : $biddingStrategy->getCurrentPrice($auction);

            if ($toRedis) {
                if ($redisPrice === null || abs($redisPrice - $dbPrice) >= 0.01) {
                    $this->warn(
                        "  Auction #{$auction->id} \"{$auction->title}\": "
                        . "Redis=".($redisPrice === null ? 'n/a' : '$'.$redisPrice)." -> DB=\${$dbPrice}"
                    );

                    if (! $dryRun) {
                        Redis::set("auction:{$auction->id}:price", (string) $dbPrice);
                    }

                    $synced++;
                } else {
                    $this->line("  Auction #{$auction->id}: OK (DB=\${$dbPrice}, Redis=\${$redisPrice})");
                }

                continue;
            }

            if ($redisPrice > $dbPrice) {
                $this->warn(
                    "  Auction #{$auction->id} \"{$auction->title}\": "
                    . "DB=\${$dbPrice} → Redis=\${$redisPrice} (drift: \$" . round($redisPrice - $dbPrice, 2) . ")"
                );

                if (! $dryRun) {
                    DB::transaction(function () use ($auction, $redisPrice) {
                        $locked = Auction::lockForUpdate()->find($auction->id);
                        if (! $locked) {
                            return;
                        }

                        $locked->current_price = $redisPrice;

                        // Re-check reserve
                        if ($locked->hasReserve() && ! $locked->reserve_met && $locked->isReserveMet()) {
                            $locked->reserve_met = true;
                        }

                        $locked->save();
                    });

                    Log::info('SyncRedisPrices: corrected', [
                        'auction_id' => $auction->id,
                        'old_price'  => $dbPrice,
                        'new_price'  => $redisPrice,
                    ]);
                }

                $synced++;
            } else {
                $this->line("  Auction #{$auction->id}: OK (DB=\${$dbPrice}, Redis=\${$redisPrice})");
            }
        }

        if ($synced === 0) {
            $this->info('All prices are in sync.');
        } else {
            $this->info(($dryRun ? 'Would sync' : 'Synced') . " {$synced} auction(s).");
        }

        if ($toRedis && ! $dryRun) {
            $health->clearRedisDegraded();
            $this->info('Redis degraded state cleared.');
        }

        return self::SUCCESS;
    }

    private function redisPrice(Auction $auction): ?float
    {
        $value = Redis::get("auction:{$auction->id}:price");

        return $value === null ? null : (float) $value;
    }
}
