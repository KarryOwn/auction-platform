<?php

namespace App\Jobs;

use App\Services\WebhookDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCoalescedBidWebhook implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [1, 5, 15];
    public int $uniqueFor = 10;

    public function __construct(
        public int $userId,
        public int $auctionId,
    ) {
        $this->onConnection((string) config('auction.webhooks_queue.connection', 'redis'));
        $this->onQueue('webhooks');
    }

    public function uniqueId(): string
    {
        return "{$this->userId}:{$this->auctionId}";
    }

    public function handle(WebhookDispatchService $service): void
    {
        $service->flushCoalescedBidPlaced($this->userId, $this->auctionId);
    }
}
