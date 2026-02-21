<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WonAuctionsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $tab = $request->input('tab', 'pending');

        $query = $user->wonAuctions()->with('media')->latest('closed_at');

        if ($tab === 'pending') {
            $query->where('payment_status', 'pending');
        } elseif ($tab === 'paid') {
            $query->where('payment_status', 'paid');
        } elseif ($tab === 'all') {
            // no filter
        }

        $auctions = $query->paginate(12)->withQueryString();

        return view('user.won-auctions', compact('auctions', 'tab'));
    }
}
