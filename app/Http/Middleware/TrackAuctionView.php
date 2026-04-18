<?php

namespace App\Http\Middleware;

use App\Models\Auction;
use App\Jobs\TrackAuctionViewJob;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackAuctionView
{
    public function handle(Request $request, Closure $next): Response
    {
        $auction = $request->route('auction');
        if (! $auction instanceof Auction) {
            return $next($request);
        }

        $userId_or_ip = $request->user() 
            ? $request->user()->id 
            : hash('sha256', $request->ip() . $request->userAgent());

        $cacheKey = "auction_view_{$auction->id}_user_{$userId_or_ip}";

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, 1, 3600);
            TrackAuctionViewJob::dispatch($auction->id)->onQueue('analytics');
        }

        return $next($request);
    }
}
