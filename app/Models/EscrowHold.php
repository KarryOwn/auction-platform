<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowHold extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'auction_id',
        'amount',
        'status',
        'captured_at',
        'released_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'captured_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    // ── Scopes ─────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForAuction(Builder $query, int $auctionId): Builder
    {
        return $query->where('auction_id', $auctionId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ─────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function markReleased(): void
    {
        $this->update([
            'status'      => self::STATUS_RELEASED,
            'released_at' => now(),
        ]);
    }

    public function markCaptured(): void
    {
        $this->update([
            'status'      => self::STATUS_CAPTURED,
            'captured_at' => now(),
        ]);
    }

    public function markRefunded(): void
    {
        $this->update([
            'status' => self::STATUS_REFUNDED,
        ]);
    }
}
