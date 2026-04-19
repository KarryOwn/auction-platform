<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'user_id',
        'question',
        'answer',
        'answered_by_id',
        'answered_at',
        'is_visible',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'is_visible' => 'boolean',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function isAnswered(): bool
    {
        return ! empty($this->answer);
    }
}