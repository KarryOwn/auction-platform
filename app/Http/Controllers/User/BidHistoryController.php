<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BidHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $user->bids()
            ->with(['auction.media', 'retractionRequest'])
            ->latest();

        // Filter by auction status
        if ($request->filled('status')) {
            $status = $request->input('status');

            if ($status === 'won') {
                $query->whereHas('auction', fn ($q) => $q->completed()->where('winner_id', $user->id));
            } elseif ($status === 'lost') {
                $query->whereHas('auction', fn ($q) => $q->completed()->where('winner_id', '!=', $user->id));
            } elseif ($status === 'active') {
                $query->whereHas('auction', fn ($q) => $q->active());
            }
        }

        // Date range filter
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $bids = $query->paginate(15)->withQueryString();

        // For each bid, determine current status
        $bids->each(function ($bid) use ($user) {
            $auction = $bid->auction;
            if ($auction->status === 'completed') {
                $bid->bid_status = $auction->winner_id === $user->id ? 'won' : 'lost';
            } elseif ($auction->status === 'cancelled') {
                $bid->bid_status = 'cancelled';
            } elseif ($auction->isActive()) {
                $highest = $auction->bids()->where('is_retracted', false)->orderByDesc('amount')->first();
                $bid->bid_status = $highest && $highest->user_id === $user->id ? 'winning' : 'outbid';
            } else {
                $bid->bid_status = 'ended';
            }

            $bid->can_request_retraction = $auction->isActive()
                && $bid->bid_status === 'winning'
                && ! $bid->is_retracted
                && $bid->retractionRequest === null;
        });

        return view('user.bid-history', compact('bids'));
    }
}
