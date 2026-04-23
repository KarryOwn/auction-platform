<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DataExportRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        // Active bids — auctions where user has bid and auction is still active
        $activeBids = $user->bids()
            ->with(['auction.media'])
            ->whereHas('auction', fn ($q) => $q->active())
            ->latest()
            ->get()
            ->unique('auction_id')
            ->take(5);

        // Mark which ones the user is winning
        $activeBids->each(function ($bid) {
            $bid->is_winning = $bid->auction->bids()->orderByDesc('amount')->value('user_id') === $bid->user_id;
        });

        // Won items pending payment
        $wonItems = $user->wonAuctions()
            ->with('media')
            ->where('payment_status', 'pending')
            ->latest('closed_at')
            ->take(5)
            ->get();

        // Watchlist — ending soonest
        $watchedItems = $user->watchedAuctions()
            ->with(['auction.media'])
            ->whereHas('auction', fn ($q) => $q->active())
            ->get()
            ->sortBy(fn ($w) => $w->auction->end_time)
            ->take(5);

        // Counts
        $activeBidCount = $user->bids()
            ->whereHas('auction', fn ($q) => $q->active())
            ->distinct('auction_id')
            ->count('auction_id');

        $wonUnpaidCount = $user->wonAuctions()
            ->where('payment_status', 'pending')
            ->count();

        $latestExportRequest = DataExportRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        return view('user.dashboard', compact(
            'activeBids',
            'wonItems',
            'watchedItems',
            'activeBidCount',
            'wonUnpaidCount',
            'user',
            'latestExportRequest',
        ));
    }
}
