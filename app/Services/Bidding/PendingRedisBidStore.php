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
