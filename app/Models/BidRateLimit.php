<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidRateLimit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'auction_id',
        'bid_count',
        'window_start',
        'window_end',
        'last_bid_at',
        'is_throttled',
    ];

    protected $casts = [
        'bid_count'    => 'integer',
        'window_start' => 'datetime',
        'window_end'   => 'datetime',
        'last_bid_at'  => 'datetime',
        'is_throttled' => 'boolean',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }


    /**
     * Record a bid hit against this rate-limit window.
     */
    public function recordBid(): void
    {
        $this->bid_count++;
        $this->last_bid_at = now();
        $this->save();
    }

    /**
     * Whether the rate-limit window has expired.
     */
    public function isWindowExpired(): bool
    {
        return now()->isAfter($this->window_end);
    }

    /**
     * Reset the window for a new period.
     */
    public function resetWindow(int $windowSeconds = 60): void
    {
        $this->bid_count = 0;
        $this->window_start = now();
        $this->window_end = now()->addSeconds($windowSeconds);
        $this->is_throttled = false;
        $this->save();
    }
}
