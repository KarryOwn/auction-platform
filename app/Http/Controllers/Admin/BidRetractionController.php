<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\BiddingStrategy;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidRetractionRequest;
use App\Services\EscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BidRetractionController extends Controller
{
    public function index(Request $request)
    {
        $query = BidRetractionRequest::with([
            'bid', 'user:id,name', 'auction:id,title,current_price', 'reviewer:id,name'
        ]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', 'pending');
        }

        $requests = $query->latest()->paginate(20);

        return view('admin.bid-retractions.index', compact('requests'));
    }

    public function approve(Request $request, BidRetractionRequest $retractionRequest): JsonResponse
    {
        if ($retractionRequest->status !== 'pending') {
            return response()->json(['error' => 'Request is already processed.'], 422);
        }

        DB::transaction(function () use ($retractionRequest, $request) {
            $bid     = $retractionRequest->bid;
            $auction = $retractionRequest->auction;
            $user    = $retractionRequest->user;

            // Mark bid as retracted
            $bid->update(['is_retracted' => true, 'retracted_at' => now()]);

            // Release escrow
            app(EscrowService::class)->releaseForUser($user, $auction);

            // Recalculate auction current price (next highest non-retracted bid)
            $newHighestBid = Bid::where('auction_id', $auction->id)
                ->where('is_retracted', false)
                ->orderByDesc('amount')
                ->first();

            $auction->update([
                'current_price' => $newHighestBid?->amount ?? $auction->starting_price,
            ]);

            // Update Redis price
            app(BiddingStrategy::class)->initializePrice($auction);

            $retractionRequest->update([
                'status'         => 'approved',
                'reviewed_by'    => $request->user()->id,
                'reviewed_at'    => now(),
                'reviewer_notes' => $request->input('notes'),
            ]);

            AuditLog::record('bid.retraction.approved', 'bid', $bid->id, [
                'auction_id' => $auction->id,
                'user_id'    => $user->id,
            ]);
        });

        return response()->json(['message' => 'Bid retraction approved.']);
    }

    public function decline(Request $request, BidRetractionRequest $retractionRequest): JsonResponse
    {
        if ($retractionRequest->status !== 'pending') {
            return response()->json(['error' => 'Request is already processed.'], 422);
        }

        $retractionRequest->update([
            'status'         => 'declined',
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
            'reviewer_notes' => $request->input('notes'),
        ]);

        AuditLog::record('bid.retraction.declined', 'bid', $retractionRequest->bid_id, [
            'auction_id' => $retractionRequest->auction_id,
            'user_id'    => $retractionRequest->user_id,
            'notes'      => $request->input('notes'),
        ]);

        return response()->json(['message' => 'Bid retraction declined.']);
    }
}
