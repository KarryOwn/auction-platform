<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\AuctionWatcher;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\AuctionEndingSoonNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyEndingSoonAuctions extends Command
{
    protected $signature = 'auctions:notify-ending-soon
                            {--minutes=30 : Threshold in minutes before auction ends}';

    protected $description = 'Send notifications for auctions ending within the threshold';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        $auctions = Auction::where('status', Auction::STATUS_ACTIVE)
            ->where('end_time', '>', now())
            ->where('end_time', '<=', now()->addMinutes($minutes))
            ->where('ending_soon_notified', false)
            ->get();

        if ($auctions->isEmpty()) {
            $this->info('No auctions ending soon.');
            return self::SUCCESS;
        }

        $this->info("Found {$auctions->count()} auction(s) ending within {$minutes} minutes.");

        foreach ($auctions as $auction) {
            $this->notifyForAuction($auction);

            $auction->update(['ending_soon_notified' => true]);
        }

        return self::SUCCESS;
    }

    protected function notifyForAuction(Auction $auction): void
    {
        // Collect watchers with notify_ending = true
        $watcherUserIds = AuctionWatcher::where('auction_id', $auction->id)
            ->where('notify_ending', true)
            ->pluck('user_id');

        // Collect unique bidders
        $bidderUserIds = Bid::where('auction_id', $auction->id)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $allUserIds = $watcherUserIds->merge($bidderUserIds)->unique();

        if ($allUserIds->isEmpty()) {
            return;
        }

        $users = User::whereIn('id', $allUserIds)->get();

        $notification = new AuctionEndingSoonNotification(
            auctionId:     $auction->id,
            auctionTitle:  $auction->title,
            endsAt:        $auction->end_time->toIso8601String(),
            timeRemaining: $auction->timeRemaining(),
            currentPrice:  (float) $auction->current_price,
        );

        foreach ($users as $user) {
            try {
                $user->notify(clone $notification);
            } catch (\Throwable $e) {
                Log::warning('NotifyEndingSoon: notification failed', [
                    'user_id'    => $user->id,
                    'auction_id' => $auction->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('NotifyEndingSoon: notifications sent', [
            'auction_id' => $auction->id,
            'count'      => $allUserIds->count(),
        ]);
    }
}
