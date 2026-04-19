<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuctionQuestionController extends Controller
{
    public function store(Request $request, Auction $auction): RedirectResponse
    {
        if ((int) $request->user()->id === (int) $auction->user_id) {
            return redirect()->back()->with('error', 'You cannot ask questions on your own auction.');
        }

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        AuctionQuestion::create([
            'auction_id' => $auction->id,
            'user_id' => $request->user()->id,
            'question' => $validated['question'],
        ]);

        return redirect()->back()->with('success', 'Your question has been posted.');
    }

    public function answer(Request $request, AuctionQuestion $question): RedirectResponse
    {
        if ((int) $request->user()->id !== (int) $question->auction->user_id) {
            abort(403, 'Only the seller can answer this question.');
        }

        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:1000'],
        ]);

        $question->update([
            'answer' => $validated['answer'],
            'answered_at' => now(),
            'answered_by_id' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Answer posted successfully.');
    }

    public function destroy(Request $request, AuctionQuestion $question): RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $isAsker = $userId === (int) $question->user_id;
        $isSeller = $userId === (int) $question->auction->user_id;

        if (! $isAsker && ! $isSeller) {
            abort(403, 'You are not allowed to delete this question.');
        }

        $question->delete();

        return redirect()->back()->with('success', 'Question removed.');
    }
}