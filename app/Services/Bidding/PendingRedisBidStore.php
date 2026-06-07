<?php

namespace App\Services\Bidding;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PendingRedisBidStore
{
    public const GLOBAL_INDEX_KEY = 'auction:pending_bids:auctions';

    public static function hashKey(int $auctionId): string
    {
        return "auction:{$auctionId}:pending_bids";
    }

    public static function indexKey(int $auctionId): string
    {
        return "auction:{$auctionId}:pending_bid_index";
    }

    public static function drainScheduledKey(int $auctionId): string
    {
        return "auction:{$auctionId}:pending_bids:drain_scheduled";
    }

    public function clearDrainScheduled(int $auctionId): void
    {
        try {
            Redis::del(self::drainScheduledKey($auctionId));
        } catch (\Throwable $e) {
            Log::warning('PendingRedisBidStore: drain schedule cleanup failed', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function markProcessed(int $auctionId, string $acceptedBidId): void
    {
        try {
            Redis::hdel(self::hashKey($auctionId), $acceptedBidId);
            Redis::zrem(self::indexKey($auctionId), $acceptedBidId);

            if ((int) Redis::hlen(self::hashKey($auctionId)) === 0) {
                Redis::zrem(self::GLOBAL_INDEX_KEY, (string) $auctionId);
            }
        } catch (\Throwable $e) {
            Log::warning('PendingRedisBidStore: cleanup failed', [
                'auction_id' => $auctionId,
                'accepted_bid_id' => $acceptedBidId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function duePendingBids(int $olderThanSeconds = 10): array
    {
        $cutoff = microtime(true) - $olderThanSeconds;
        $pending = [];

        $auctionIds = Redis::zrangebyscore(self::GLOBAL_INDEX_KEY, '-inf', (string) $cutoff);

        foreach ($auctionIds as $auctionId) {
            $auctionId = (int) $auctionId;
            $entries = $this->hashEntries(self::hashKey($auctionId));

            if ($entries === []) {
                Redis::zrem(self::GLOBAL_INDEX_KEY, (string) $auctionId);
                continue;
            }

            foreach ($entries as $acceptedBidId => $payloadJson) {
                $payload = json_decode((string) $payloadJson, true);

                if (! is_array($payload)) {
                    Log::warning('PendingRedisBidStore: invalid pending bid payload', [
                        'auction_id' => $auctionId,
                        'accepted_bid_id' => $acceptedBidId,
                    ]);
                    continue;
                }

                if ((float) ($payload['accepted_at'] ?? 0) > $cutoff) {
                    continue;
                }

                $payload['accepted_bid_id'] = (string) $acceptedBidId;
                $pending[] = $payload;
            }
        }

        return $pending;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingBidsForAuction(int $auctionId, int $limit = 100): array
    {
        $acceptedBidIds = Redis::zrange(self::indexKey($auctionId), 0, max(0, $limit - 1));

        if ($acceptedBidIds === [] || $acceptedBidIds === false || $acceptedBidIds === null) {
            return [];
        }

        $pending = [];

        foreach ($acceptedBidIds as $acceptedBidId) {
            $payloadJson = Redis::hget(self::hashKey($auctionId), (string) $acceptedBidId);

            if (! $payloadJson) {
                Redis::zrem(self::indexKey($auctionId), (string) $acceptedBidId);
                continue;
            }

            $payload = json_decode((string) $payloadJson, true);

            if (! is_array($payload)) {
                Log::warning('PendingRedisBidStore: invalid pending bid payload', [
                    'auction_id' => $auctionId,
                    'accepted_bid_id' => $acceptedBidId,
                ]);
                continue;
            }

            $payload['accepted_bid_id'] = (string) $acceptedBidId;
            $pending[] = $payload;
        }

        usort($pending, static function (array $first, array $second): int {
            return ((float) ($first['accepted_at'] ?? 0)) <=> ((float) ($second['accepted_at'] ?? 0));
        });

        return $pending;
    }

    public function pendingCount(int $auctionId): int
    {
        return (int) Redis::hlen(self::hashKey($auctionId));
    }

    /**
     * @return array<string, string>
     */
    private function hashEntries(string $key): array
    {
        $entries = Redis::hgetall($key);

        if ($entries === [] || $entries === false || $entries === null) {
            return [];
        }

        if (array_is_list($entries)) {
            $normalized = [];

            for ($i = 0, $count = count($entries); $i < $count; $i += 2) {
                if (isset($entries[$i + 1])) {
                    $normalized[(string) $entries[$i]] = (string) $entries[$i + 1];
                }
            }

            return $normalized;
        }

        return array_map(static fn ($value) => (string) $value, $entries);
    }
}
