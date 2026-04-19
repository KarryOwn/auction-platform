<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionRating;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuctionRatingController extends Controller
{
    public function create(Request $request, Auction $auction): RedirectResponse|View
    {
        $user = $request->user();
        $ratee = $this->resolveRatee($auction, $user->id);

        if (! $ratee) {
            return redirect()->route('auctions.show', $auction)
                ->with('error', 'You are not allowed to rate this auction.');
        }

        if (! $this->canRateAuction($auction, $user->id)) {
            return redirect()->route('auctions.show', $auction)
                ->with('error', 'Ratings are available only after completion and captured payment.');
        }

        if ($this->hasAlreadyRated($auction->id, $user->id)) {
            return redirect()->route('auctions.show', $auction)
                ->with('error', 'You have already submitted a rating for this auction.');
        }

        return view('auctions.rate', compact('auction', 'ratee'));
    }

    public function store(Request $request, Auction $auction): RedirectResponse
    {
        $user = $request->user();
        $ratee = $this->resolveRatee($auction, $user->id);

        if (! $ratee) {
            return redirect()->route('auctions.show', $auction)
                ->with('error', 'You are not allowed to rate this auction.');
        }

        if (! $this->canRateAuction($auction, $user->id)) {
            return redirect()->route('auctions.show', $auction)
                ->with('error', 'Ratings are available only after completion and captured payment.');
        }

        if ($this->hasAlreadyRated($auction->id, $user->id)) {
            return redirect()->route('auctions.show', $auction)
                ->with('error', 'You have already submitted a rating for this auction.');
        }

        $validated = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        AuctionRating::create([
            'auction_id' => $auction->id,
            'rater_id' => $user->id,
            'ratee_id' => $ratee->id,
            'role' => $ratee->id === $auction->user_id ? 'seller' : 'buyer',
            'score' => $validated['score'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return redirect()->route('auctions.show', $auction)
            ->with('success', 'Thank you for your rating.');
    }

    private function canRateAuction(Auction $auction, int $userId): bool
    {
        return $auction->status === Auction::STATUS_COMPLETED
            && $auction->payment_status === 'captured'
            && in_array($userId, [(int) $auction->user_id, (int) $auction->winner_id], true);
    }

    private function hasAlreadyRated(int $auctionId, int $raterId): bool
    {
        return AuctionRating::where('auction_id', $auctionId)
            ->where('rater_id', $raterId)
            ->exists();
    }

    private function resolveRatee(Auction $auction, int $raterId): ?User
    {
        if (! $auction->winner_id) {
            return null;
        }

        if ($raterId === (int) $auction->winner_id) {
            return User::find($auction->user_id);
        }

        if ($raterId === (int) $auction->user_id) {
            return User::find($auction->winner_id);
        }

        return null;
    }
}