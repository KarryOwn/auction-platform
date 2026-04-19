<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Dispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DisputeController extends Controller
{
    public function create(Auction $auction): View|RedirectResponse
    {
        $userId = (int) auth()->id();

        $isParticipant = $userId === (int) $auction->winner_id || $userId === (int) $auction->user_id;
        if (! $isParticipant) {
            abort(403, 'Only auction participants can open disputes.');
        }

        if ($auction->status !== Auction::STATUS_COMPLETED) {
            return redirect()
                ->route('user.won-auctions')
                ->with('error', 'Disputes can only be opened for completed auctions.');
        }

        if ((string) $auction->payment_status !== 'paid') {
            return redirect()
                ->route('user.won-auctions')
                ->with('error', 'Disputes can only be opened after payment is captured.');
        }

        $existing = Dispute::query()
            ->where('auction_id', $auction->id)
            ->where('claimant_id', $userId)
            ->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_UNDER_REVIEW])
            ->exists();

        if ($existing) {
            return redirect()
                ->route('user.won-auctions')
                ->with('error', 'You already have an open dispute for this auction.');
        }

        return view('user.disputes.create', [
            'auction' => $auction,
        ]);
    }

    public function store(Request $request, Auction $auction): RedirectResponse
    {
        $userId = (int) auth()->id();

        $isParticipant = $userId === (int) $auction->winner_id || $userId === (int) $auction->user_id;
        if (! $isParticipant) {
            abort(403, 'Only auction participants can open disputes.');
        }

        if ($auction->status !== Auction::STATUS_COMPLETED) {
            return redirect()
                ->route('user.won-auctions')
                ->with('error', 'Disputes can only be opened for completed auctions.');
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['item_not_received', 'not_as_described', 'non_payment', 'other'])],
            'description' => ['required', 'string', 'max:3000'],
            'evidence_urls' => ['nullable', 'array'],
            'evidence_urls.*' => ['nullable', 'url', 'max:2048'],
        ]);

        $respondentId = $userId === (int) $auction->winner_id
            ? (int) $auction->user_id
            : (int) $auction->winner_id;

        if ($respondentId <= 0) {
            return redirect()
                ->route('user.won-auctions')
                ->with('error', 'Unable to determine dispute counterparty.');
        }

        Dispute::create([
            'auction_id' => $auction->id,
            'claimant_id' => $userId,
            'respondent_id' => $respondentId,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'status' => Dispute::STATUS_OPEN,
            'evidence_urls' => array_values(array_filter($validated['evidence_urls'] ?? [])),
        ]);

        return redirect()
            ->route('user.won-auctions')
            ->with('success', 'Dispute submitted successfully. Our team will review it shortly.');
    }
}
