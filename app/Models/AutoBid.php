<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutoBid extends Model
{
    use HasFactory;

    public const DEFAULT_MAX_AUTO_BIDS = 3;

    protected $fillable = [
        'auction_id',
        'user_id',
        'max_amount',
        'bid_increment',
        'is_active',
        'max_auto_bids',
        'auto_bids_used',
        'last_triggered_at',
    ];

    protected $casts = [
        'max_amount'        => 'decimal:2',
        'bid_increment'     => 'decimal:2',
        'is_active'         => 'boolean',
        'max_auto_bids'     => 'integer',
        'auto_bids_used'    => 'integer',
        'last_triggered_at' => 'datetime',
    ];


    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }


    public function scopeWithBudget(Builder $query, float $currentPrice): Builder
    {
        return $query->where('max_amount', '>', $currentPrice);
    }

    /**
     * Auto-bids for a specific auction ordered by max amount desc.
     */
    public function scopeForAuction(Builder $query, int $auctionId): Builder
    {
        return $query->where('auction_id', $auctionId)
                     ->orderByDesc('max_amount');
    }

    /**
     * Whether this auto-bid can still place a bid at the given price.
     */
    public function canBidAt(float $price): bool
    {
        return (float) $this->max_amount >= $price;
    }

    /**
     * Whether this auto-bid still has remaining trigger slots.
     */
    public function hasRemainingTriggers(): bool
    {
        $max = max(1, (int) ($this->max_auto_bids ?? self::DEFAULT_MAX_AUTO_BIDS));

        return (int) ($this->auto_bids_used ?? 0) < $max;
    }

    /**
     * Mark this auto-bid as just triggered.
     */
    public function markTriggered(): void
    {
        $max = max(1, (int) ($this->max_auto_bids ?? self::DEFAULT_MAX_AUTO_BIDS));
        $used = (int) ($this->auto_bids_used ?? 0) + 1;

        $this->update([
            'last_triggered_at' => now(),
            'auto_bids_used' => $used,
            'is_active' => $used < $max,
        ]);
    }
}
