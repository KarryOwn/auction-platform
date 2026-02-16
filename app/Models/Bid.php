<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    use HasFactory;


    public const TYPE_MANUAL = 'manual';
    public const TYPE_AUTO   = 'auto';
    public const TYPE_PROXY  = 'proxy';


    protected $fillable = [
        'auction_id',
        'user_id',
        'amount',
        'bid_type',
        'previous_amount',
        'ip_address',
        'user_agent',
        'auto_bid_id',
        'is_snipe_bid',
    ];


    protected $casts = [
        'amount'          => 'decimal:2',
        'previous_amount' => 'decimal:2',
        'is_snipe_bid'    => 'boolean',
        'created_at'      => 'datetime:Y-m-d\TH:i:s.u',
    ];


    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function autoBid(): BelongsTo
    {
        return $this->belongsTo(AutoBid::class);
    }


    public function scopeManual(Builder $query): Builder
    {
        return $query->where('bid_type', self::TYPE_MANUAL);
    }

    public function scopeAuto(Builder $query): Builder
    {
        return $query->where('bid_type', self::TYPE_AUTO);
    }

    public function scopeSnipeBids(Builder $query): Builder
    {
        return $query->where('is_snipe_bid', true);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }


    public function isAuto(): bool
    {
        return $this->bid_type === self::TYPE_AUTO;
    }

    public function isManual(): bool
    {
        return $this->bid_type === self::TYPE_MANUAL;
    }

    /**
     * The dollar increase over the previous bid.
     */
    public function bidIncrement(): float
    {
        if ($this->previous_amount === null) {
            return 0;
        }

        return round((float) $this->amount - (float) $this->previous_amount, 2);
    }
}
