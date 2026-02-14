<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionSnapshot extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'auction_id',
        'price',
        'bid_count',
        'unique_bidders',
        'watcher_count',
        'metadata',
        'captured_at',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'bid_count'      => 'integer',
        'unique_bidders' => 'integer',
        'watcher_count'  => 'integer',
        'metadata'       => 'array',
        'captured_at'    => 'datetime',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public static function capture(Auction $auction, array $extraMetadata = []): static
    {
        return static::create([
            'auction_id'     => $auction->id,
            'price'          => $auction->current_price,
            'bid_count'      => $auction->bid_count,
            'unique_bidders' => $auction->unique_bidder_count,
            'watcher_count'  => $auction->watchers()->count(),
            'metadata'       => array_merge([
                'status'          => $auction->status,
                'reserve_met'     => $auction->reserve_met,
                'extension_count' => $auction->extension_count,
            ], $extraMetadata),
            'captured_at'    => now(),
        ]);
    }
}
