<?php

namespace App\Jobs;

use App\Models\Bid;
use App\Models\User;
use App\Notifications\OutbidNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SendCoalescedOutbidNotification implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 60];
    public int $uniqueFor = 60;

    public function __construct(
        public int $auctionId,
        public int $userId,
        public bool $isWatcher = false,
    ) {
        $this->onConnection((string) config('auction.notifications_queue.connection', 'redis'));
        $this->onQueue('notifications');
    }

    public static function stateKey(int $auctionId, int $userId, bool $isWatcher = false): string
    {
        $scope = $isWatcher ? 'watcher' : 'bidder';

        return "auction:{$auctionId}:outbid_notification:{$scope}:{$userId}";
    }

    public function uniqueId(): string
    {
        return "{$this->auctionId}:{$this->userId}:".($this->isWatcher ? 'watcher' : 'bidder');
    }

    public function handle(): void
    {
        $key = self::stateKey($this->auctionId, $this->userId, $this->isWatcher);
        $payloadJson = Redis::get($key);

        if (! $payloadJson) {
            return;
        }

        Redis::del($key);

        $payload = json_decode((string) $payloadJson, true);

        if (! is_array($payload)) {
            Log::warning('SendCoalescedOutbidNotification: invalid payload', [
                'auction_id' => $this->auctionId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        if ($this->userHasAlreadyCaughtUp((float) ($payload['outbid_amount'] ?? 0))) {
            return;
        }

        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $user->notify(new OutbidNotification(
            auctionId: (int) $payload['auction_id'],
            auctionTitle: (string) $payload['auction_title'],
            outbidAmount: (float) $payload['outbid_amount'],
            yourAmount: (float) ($payload['your_amount'] ?? 0),
            isWatcher: (bool) ($payload['is_watcher'] ?? false),
        ));
    }

    private function userHasAlreadyCaughtUp(float $outbidAmount): bool
    {
        if ($outbidAmount <= 0) {
            return false;
        }

        return Bid::where('auction_id', $this->auctionId)
            ->where('user_id', $this->userId)
            ->where('amount', '>=', $outbidAmount)
            ->exists();
    }
}
