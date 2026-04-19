<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $seller = $request->user();
        $from = now()->subDays(7)->startOfDay();
        $to = now()->addDays(30)->endOfDay();

        $auctions = Auction::query()
            ->where('user_id', $seller->id)
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('start_time', [$from, $to])
                    ->orWhereBetween('end_time', [$from, $to]);
            })
            ->select(['id', 'title', 'status', 'start_time', 'end_time'])
            ->orderBy('start_time')
            ->get();

        $auctionsByDate = collect();

        foreach ($auctions as $auction) {
            if ($auction->start_time instanceof Carbon) {
                $startKey = $auction->start_time->toDateString();
                $auctionsByDate->put(
                    $startKey,
                    $auctionsByDate->get($startKey, collect())->push([
                        'type' => 'start',
                        'auction' => $auction,
                    ])
                );
            }

            if ($auction->end_time instanceof Carbon) {
                $endKey = $auction->end_time->toDateString();
                $auctionsByDate->put(
                    $endKey,
                    $auctionsByDate->get($endKey, collect())->push([
                        'type' => 'end',
                        'auction' => $auction,
                    ])
                );
            }
        }

        $auctionsByDate = $auctionsByDate
            ->sortKeys()
            ->map(fn ($items) => $items->sortBy(fn ($item) => [
                $item['type'] === 'start' ? 0 : 1,
                $item['auction']->start_time?->timestamp ?? 0,
                $item['auction']->end_time?->timestamp ?? 0,
            ])->values());

        $today = today()->toDateString();
        $weeks = 5;

        return view('seller.auctions.schedule', compact('auctionsByDate', 'today', 'weeks'));
    }
}
