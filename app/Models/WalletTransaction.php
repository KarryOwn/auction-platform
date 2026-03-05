<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_DEPOSIT       = 'deposit';
    public const TYPE_WITHDRAWAL    = 'withdrawal';
    public const TYPE_BID_HOLD      = 'bid_hold';
    public const TYPE_BID_RELEASE   = 'bid_release';
    public const TYPE_PAYMENT       = 'payment';
    public const TYPE_REFUND        = 'refund';
    public const TYPE_SELLER_CREDIT = 'seller_credit';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'stripe_session_id',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Scopes ─────────────────────────────────

    public function scopeDeposits(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DEPOSIT);
    }

    public function scopeHolds(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BID_HOLD);
    }

    public function scopePayments(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PAYMENT);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ─────────────────────────────────

    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPOSIT,
            self::TYPE_BID_RELEASE,
            self::TYPE_REFUND,
            self::TYPE_SELLER_CREDIT,
        ]);
    }

    public function isDebit(): bool
    {
        return in_array($this->type, [
            self::TYPE_WITHDRAWAL,
            self::TYPE_BID_HOLD,
            self::TYPE_PAYMENT,
        ]);
    }
}
