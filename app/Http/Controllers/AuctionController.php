<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Auction;

class AuctionController extends Controller
{
    public function index() {
        $auctions = Auction::where('status', 'active')->orderBy('end_time', 'asc')->paginate(12);
        return view('auctions.index', compact('auctions'));
    }

    public function show(Auction $auction) {
        return view('auctions.show', compact('auction'));
    }
}
