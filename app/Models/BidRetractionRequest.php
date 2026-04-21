<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidRetractionRequest extends Model
{
    protected $fillable = [
        'bid_id',
        'user_id',
        'auction_id',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
