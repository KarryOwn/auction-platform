<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService,
    ) {}

    public function show(Auction $auction)
    {
        $auction->load(['winner', 'seller', 'invoice']);

        return view('admin.refunds.show', compact('auction'));
    }

    public function process(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'type'   => 'required|in:full,partial',
            'amount' => 'required_if:type,partial|nullable|numeric|min:0.01',
        ]);

        if ($auction->payment_status !== 'paid') {
            return back()->withErrors(['auction' => 'This auction has not been paid yet or has already been refunded.']);
        }

        if ($validated['type'] === 'full') {
            $this->refundService->refundAuctionPayment($auction, $validated['reason']);
        } else {
            $amount = (float) $validated['amount'];
            if ($amount > (float) $auction->winning_bid_amount) {
                return back()->withErrors(['amount' => 'Refund amount cannot exceed the winning bid amount.']);
            }
            $this->refundService->refundPartial($auction, $amount, $validated['reason']);
        }

        return redirect()->route('admin.auctions.show', $auction)
            ->with('success', 'Refund processed successfully.');
    }
}
