<?php

namespace App\Policies;

use App\Models\Auction;
use App\Models\User;

class AuctionPolicy
{
    public function create(User $user): bool
    {
        return $user->canCreateAuctions();
    }

    public function update(User $user, Auction $auction): bool
    {
        return $auction->user_id === $user->id
            && in_array($auction->status, [Auction::STATUS_DRAFT, Auction::STATUS_ACTIVE], true);
    }

    public function delete(User $user, Auction $auction): bool
    {
        return $auction->user_id === $user->id && $auction->isDraft();
    }

    public function cancel(User $user, Auction $auction): bool
    {
        return $auction->user_id === $user->id
            && $auction->status === Auction::STATUS_ACTIVE
            && (int) $auction->bid_count === 0;
    }

    public function publish(User $user, Auction $auction): bool
    {
        return $auction->user_id === $user->id
            && $auction->isDraft();
    }

    public function uploadMedia(User $user, Auction $auction): bool
    {
        return $auction->user_id === $user->id
            && in_array($auction->status, [Auction::STATUS_DRAFT, Auction::STATUS_ACTIVE], true);
    }
}
