<?php

namespace App\Services\Bidding;

use Illuminate\Support\Facades\Cache;

class BiddingEngineHealth
{
    private const REDIS_DEGRADED_KEY = 'bidding:redis:degraded';

    public function redisIsDegraded(): bool
    {
        return (bool) $this->cache()->get(self::REDIS_DEGRADED_KEY, false);
    }

    public function markRedisDegraded(?string $reason = null): void
    {
        $this->cache()->put(
            self::REDIS_DEGRADED_KEY,
            [
                'reason' => $reason,
                'marked_at' => now()->toIso8601String(),
            ],
            now()->addSeconds((int) config('auction.redis_health.degraded_ttl', 15)),
        );
    }

    public function clearRedisDegraded(): void
    {
        $this->cache()->forget(self::REDIS_DEGRADED_KEY);
    }

    private function cache()
    {
        return Cache::store((string) config('auction.redis_health.cache_store', 'file'));
    }
}
