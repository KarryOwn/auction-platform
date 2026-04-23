<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        $sort = $request->input('sort', 'ending_soon');

        $watchedItems = $user->watchedAuctions()
            ->with(['auction.media'])
            ->get();

        // Split into active and ended
        $active = $watchedItems->filter(fn ($w) => $w->auction->isActive());
        $ended = $watchedItems->filter(fn ($w) => !$w->auction->isActive());

        // Sort active items
        if ($sort === 'ending_soon') {
            $active = $active->sortBy(fn ($w) => $w->auction->end_time);
        } else {
            $active = $active->sortByDesc('created_at');
        }

        return view('user.watchlist', compact('active', 'ended', 'sort'));
    }
}
