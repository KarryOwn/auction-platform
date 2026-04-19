<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStorefrontRequest;
use App\Models\Auction;
use App\Models\AuctionRating;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class StorefrontController extends Controller
{
    public function show(string $slug)
    {
        $seller = User::query()
            ->where('seller_slug', $slug)
            ->verifiedSellers()
            ->firstOrFail();

        $activeAuctions = Auction::query()
            ->where('user_id', $seller->id)
            ->where('status', Auction::STATUS_ACTIVE)
            ->latest('end_time')
            ->paginate(12, ['*'], 'active_page');

        $completedAuctions = Auction::query()
            ->where('user_id', $seller->id)
            ->where('status', Auction::STATUS_COMPLETED)
            ->latest('closed_at')
            ->paginate(12, ['*'], 'completed_page');

        $stats = [
            'total_listed' => Auction::query()->where('user_id', $seller->id)->count(),
            'total_completed' => Auction::query()->where('user_id', $seller->id)->where('status', Auction::STATUS_COMPLETED)->count(),
        ];

        $averageRating = AuctionRating::averageForUser($seller->id);
        $ratingCount = AuctionRating::where('ratee_id', $seller->id)->count();

        return view('seller.storefront.show', compact(
            'seller',
            'activeAuctions',
            'completedAuctions',
            'stats',
            'averageRating',
            'ratingCount',
        ));
    }

    public function edit()
    {
        return view('seller.storefront.edit', ['user' => auth()->user()]);
    }

    public function update(UpdateStorefrontRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = [
            'seller_bio' => $request->input('seller_bio'),
            'seller_slug' => $request->input('seller_slug'),
        ];

        if ($request->hasFile('seller_avatar')) {
            $data['seller_avatar_path'] = $request->file('seller_avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return redirect()->route('seller.storefront.edit')->with('status', 'Storefront updated.');
    }
}
