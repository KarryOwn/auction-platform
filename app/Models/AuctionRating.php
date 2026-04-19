<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'rater_id',
        'ratee_id',
        'role',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }

    public function scopeForSeller(Builder $query, int $userId): Builder
    {
        return $query->where('ratee_id', $userId)
            ->where('role', 'seller');
    }

    public function scopeForBuyer(Builder $query, int $userId): Builder
    {
        return $query->where('ratee_id', $userId)
            ->where('role', 'buyer');
    }

    public static function averageForUser(int $userId): float
    {
        return (float) (static::where('ratee_id', $userId)->avg('score') ?? 0.0);
    }
}