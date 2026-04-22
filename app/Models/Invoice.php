<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_ISSUED   = 'issued';
    public const STATUS_PAID     = 'paid';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'invoice_number',
        'auction_id',
        'buyer_id',
        'seller_id',
        'subtotal',
        'platform_fee',
        'seller_amount',
        'total',
        'currency',
        'status',
        'issued_at',
        'paid_at',
        'pdf_path',
        'metadata',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'platform_fee'  => 'decimal:2',
        'seller_amount' => 'decimal:2',
        'total'         => 'decimal:2',
        'issued_at'     => 'datetime',
        'paid_at'       => 'datetime',
        'metadata'      => 'array',
    ];

    // ── Relationships ──────────────────────────

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    // ── Scopes ─────────────────────────────────

    public function scopeForBuyer(Builder $query, int $userId): Builder
    {
        return $query->where('buyer_id', $userId);
    }

    public function scopeForSeller(Builder $query, int $userId): Builder
    {
        return $query->where('seller_id', $userId);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    // ── Helpers ─────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function markPaid(): void
    {
        $this->update([
            'status'  => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function markRefunded(): void
    {
        $this->update([
            'status' => self::STATUS_REFUNDED,
        ]);
    }

    public function getCommissionRateAttribute(): ?float
    {
        $metadataRate = $this->metadata['commission_rate'] ?? null;
        if ($metadataRate !== null) {
            return (float) $metadataRate;
        }

        $subtotal = (float) $this->subtotal;
        if ($subtotal <= 0) {
            return null;
        }

        return round(((float) $this->platform_fee / $subtotal), 4);
    }

    public function getCommissionRatePercentAttribute(): ?float
    {
        return $this->commission_rate !== null
            ? round($this->commission_rate * 100, 2)
            : null;
    }

    /**
     * Generate the next sequential invoice number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';

        $latest = static::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->lockForUpdate()
            ->value('invoice_number');

        if ($latest) {
            $sequence = (int) substr($latest, strlen($prefix)) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }
}
