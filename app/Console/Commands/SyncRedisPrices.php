<?php

namespace App\Console\Commands;

use App\Contracts\BiddingStrategy;
use App\Models\Auction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-off recovery command: syncs Redis prices back to the database
 * for any active auctions where the DB is behind Redis.
 */
class SyncRedisPrices extends Command
{
    protected $signature = 'auction:sync-prices
                            {--dry-run : Show what would be updated without making changes}
                            {--auction= : Sync a specific auction by ID}';

    protected $description = 'Sync auction prices from Redis to the database (recovery tool)';

    public function handle(BiddingStrategy $biddingStrategy): int
    {
        $query = Auction::where('status', Auction::STATUS_ACTIVE);

        if ($auctionId = $this->option('auction')) {
            $query->where('id', $auctionId);
        }

        $auctions = $query->get();
        $dryRun   = $this->option('dry-run');
        $synced   = 0;

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Checking {$auctions->count()} active auction(s)...");

        foreach ($auctions as $auction) {
            $dbPrice    = (float) $auction->current_price;
            $redisPrice = $biddingStrategy->getCurrentPrice($auction);

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

        return self::SUCCESS;
    }
}
