<?php

namespace App\Jobs;

use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrackAuctionViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly string $auctionId)
    {
        $this->onQueue('analytics');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Auction::where('id', $this->auctionId)->increment('views_count');
    }
}
