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
    | Platform Fee (Percentage)
    |--------------------------------------------------------------------------
    |
    | The percentage of the winning bid amount charged as a platform fee.
    | The seller receives (100 - fee)% of the winning bid.
    |
    */
    'platform_fee_percent' => (float) env('AUCTION_PLATFORM_FEE_PERCENT', 5.0),

    /*
    |--------------------------------------------------------------------------
    | Payment Deadline (Hours)
    |--------------------------------------------------------------------------
    |
    | Hours after auction close before payment deadline expires.
    | With auto-capture enabled, this serves as a reference only.
    |
    */
    'payment_deadline_hours' => (int) env('AUCTION_PAYMENT_DEADLINE_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Minimum Bid Increment
    |--------------------------------------------------------------------------
    */
    'min_bid_increment' => (float) env('AUCTION_MIN_INCREMENT', 1.00),

    'supported_currencies' => ['USD', 'EUR', 'GBP', 'JPY', 'VND'],

    'images' => [
        'max_per_auction' => (int) env('AUCTION_IMAGES_MAX_PER_AUCTION', 10),
        'max_size_kb' => (int) env('AUCTION_IMAGES_MAX_SIZE_KB', 5120),
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'conversions' => [
            'thumbnail' => [300, 300],
            'gallery' => [800, 600],
            'full' => [1920, 1080],
        ],
    ],

    'video' => [
        'allowed_domains' => [
            'youtube.com',
            'www.youtube.com',
            'youtu.be',
            'vimeo.com',
            'player.vimeo.com',
        ],
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'max_depth' => 3,
        'max_per_auction' => 3,
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    */
    'tags' => [
        'max_per_auction' => 10,
        'min_length' => 2,
        'max_length' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Conditions
    |--------------------------------------------------------------------------
    */
    'conditions' => [
        'new' => 'New',
        'like_new' => 'Like New',
        'used_good' => 'Used - Good',
        'used_fair' => 'Used - Fair',
        'refurbished' => 'Refurbished',
        'for_parts' => 'For Parts / Not Working',
    ],

];
