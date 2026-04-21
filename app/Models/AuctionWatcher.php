<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionWatcher extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'user_id',
        'notify_outbid',
        'notify_ending',
        'notify_cancelled',
        'outbid_threshold_amount',
        'price_alert_at',
        'price_alert_sent',
    ];

    protected $casts = [
        'notify_outbid'           => 'boolean',
        'notify_ending'           => 'boolean',
        'notify_cancelled'        => 'boolean',
        'outbid_threshold_amount' => 'decimal:2',
        'price_alert_at'          => 'decimal:2',
        'price_alert_sent'        => 'boolean',
    ];


    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
