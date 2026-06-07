<?php

use App\Contracts\BiddingStrategy;
use App\Services\Bidding\BiddingEngineHealth;
use App\Services\Bidding\PessimisticSqlEngine;

test('degraded redis state resolves sql engine without probing redis', function () {
    config(['auction.engine' => 'redis']);

    app(BiddingEngineHealth::class)->markRedisDegraded('test outage');
    app()->forgetInstance(BiddingStrategy::class);

    expect(app(BiddingStrategy::class))->toBeInstanceOf(PessimisticSqlEngine::class);

    app(BiddingEngineHealth::class)->clearRedisDegraded();
});
