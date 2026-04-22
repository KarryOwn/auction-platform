<?php

namespace App\Support;

final class ApiAbilities
{
    public const AUCTIONS_READ = 'auctions:read';
    public const AUCTIONS_WRITE = 'auctions:write';
    public const BIDS_READ = 'bids:read';
    public const BIDS_PLACE = 'bids:place';
    public const WATCHLIST_READ = 'watchlist:read';
    public const WATCHLIST_WRITE = 'watchlist:write';
    public const WALLET_READ = 'wallet:read';
    public const PROFILE_READ = 'profile:read';

    public const DEFAULT_ABILITIES = [
        self::AUCTIONS_READ,
        self::BIDS_READ,
    ];

    public const ALL = [
        self::AUCTIONS_READ,
        self::AUCTIONS_WRITE,
        self::BIDS_READ,
        self::BIDS_PLACE,
        self::WATCHLIST_READ,
        self::WATCHLIST_WRITE,
        self::WALLET_READ,
        self::PROFILE_READ,
    ];
}
