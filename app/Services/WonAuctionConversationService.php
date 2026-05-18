<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Conversation;

class WonAuctionConversationService
{
    public function ensureForAuction(Auction $auction): ?Conversation
    {
        if (! $auction->winner_id) {
            return null;
        }

        $conversation = Conversation::firstOrCreate(
            [
                'auction_id' => $auction->id,
                'buyer_id' => $auction->winner_id,
            ],
            [
                'seller_id' => $auction->user_id,
                'last_message_at' => now(),
            ],
        );

        $updates = [
            'seller_id' => $auction->user_id,
            'is_closed' => false,
        ];

        if ($conversation->last_message_at === null) {
            $updates['last_message_at'] = now();
        }

        if ($conversation->delivery_status === null) {
            $updates['delivery_status'] = Conversation::DELIVERY_PENDING;
            $updates['delivery_updated_at'] = now();
        }

        if ($conversation->isDirty() || $updates !== []) {
            $conversation->update($updates);
        }

        return $conversation->fresh(['auction', 'buyer', 'seller']);
    }
}
