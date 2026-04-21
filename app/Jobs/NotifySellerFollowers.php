<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\User;
use App\Notifications\NewSellerListingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySellerFollowers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $auctionId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $auction = Auction::find($this->auctionId);
        if (! $auction || ! $auction->isActive()) {
            return;
        }

        $seller = $auction->seller;

        User::whereHas('following', fn ($q) => $q->where('seller_id', $seller->id)
            ->where('notify_new_listings', true)
        )->where('id', '!=', $seller->id)
        ->chunk(100, function ($followers) use ($auction) {
            foreach ($followers as $follower) {
                try {
                    $follower->notify(new NewSellerListingNotification($auction));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('NotifySellerFollowers failed', [
                        'follower_id' => $follower->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
