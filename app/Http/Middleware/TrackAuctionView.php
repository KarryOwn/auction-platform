<?php

namespace App\Http\Middleware;

use App\Models\Auction;
use App\Models\AuctionView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAuctionView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $auction = $request->route('auction');
        if (! $auction instanceof Auction) {
            return $response;
        }

        if (! $request->hasSession()) {
            return $response;
        }

        $sessionId = $request->session()->getId();
        $user = $request->user();

        if ($user && $user->id === $auction->user_id) {
            return $response;
        }

        $alreadyViewed = AuctionView::where('auction_id', $auction->id)
            ->where('session_id', $sessionId)
            ->where('viewed_at', '>=', now()->subHour())
            ->exists();

        if (! $alreadyViewed) {
            AuctionView::create([
                'auction_id' => $auction->id,
                'user_id' => $user?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referrer' => $request->headers->get('referer'),
                'session_id' => $sessionId,
                'viewed_at' => now(),
            ]);
        }

        return $response;
    }
}
