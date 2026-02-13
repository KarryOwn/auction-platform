<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Auction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'starting_price',
        'current_price',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'start_time'     => 'datetime',
        'end_time'       => 'datetime',
        'starting_price' => 'decimal:2',
        'current_price'  => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function latestBid(): HasOne
    {
        return $this->hasOne(Bid::class)->latestOfMany();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReportedAuction::class);
    }

    // ── Scopes ───────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->where('end_time', '>', now());
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeEndingSoon(Builder $query, int $minutes = 15): Builder
    {
        return $query->active()
                     ->where('end_time', '<=', now()->addMinutes($minutes))
                     ->orderBy('end_time', 'asc');
    }

    // ── Helpers ──────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_time->isFuture();
    }

    public function hasEnded(): bool
    {
        return $this->end_time->isPast();
    }
}