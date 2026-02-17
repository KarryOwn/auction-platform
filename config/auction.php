<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bidding Engine
    |--------------------------------------------------------------------------
    |
    | Choose which bidding engine to use: 'redis' or 'sql'.
    | Redis is recommended for high-frequency platforms.
    |
    */
    'engine' => env('AUCTION_ENGINE', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'max_bids'       => (int) env('AUCTION_RATE_LIMIT_MAX', 10),
        'window_seconds' => (int) env('AUCTION_RATE_LIMIT_WINDOW', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-Snipe Defaults
    |--------------------------------------------------------------------------
    */
    'snipe' => [
        'threshold_seconds' => (int) env('AUCTION_SNIPE_THRESHOLD', 30),
        'extension_seconds' => (int) env('AUCTION_SNIPE_EXTENSION', 30),
        'max_extensions'    => (int) env('AUCTION_SNIPE_MAX_EXTENSIONS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot Interval (minutes)
    |--------------------------------------------------------------------------
    */
    'snapshot_interval' => (int) env('AUCTION_SNAPSHOT_INTERVAL', 2),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('AUCTION_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Minimum Bid Increment
    |--------------------------------------------------------------------------
    */
    'min_bid_increment' => (float) env('AUCTION_MIN_INCREMENT', 1.00),

    /*
    |--------------------------------------------------------------------------
    | Seller Insights
    |--------------------------------------------------------------------------
    */
    'insights' => [
        'suggestion_lookback_days' => (int) env('AUCTION_INSIGHTS_LOOKBACK_DAYS', 90),
        'starting_price_factor' => (float) env('AUCTION_INSIGHTS_START_FACTOR', 0.65),
        'reserve_price_factor' => (float) env('AUCTION_INSIGHTS_RESERVE_FACTOR', 0.87),
        'min_similar_auctions' => (int) env('AUCTION_INSIGHTS_MIN_SIMILAR', 5),
    ],

];
