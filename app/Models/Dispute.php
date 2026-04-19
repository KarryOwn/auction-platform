<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED_BUYER = 'resolved_buyer';
    public const STATUS_RESOLVED_SELLER = 'resolved_seller';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'auction_id',
        'claimant_id',
        'respondent_id',
        'type',
        'description',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'evidence_urls',
    ];

    protected $casts = [
        'evidence_urls' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RESOLVED_BUYER, self::STATUS_RESOLVED_SELLER, self::STATUS_CLOSED]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'Open',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_RESOLVED_BUYER => 'Resolved (Buyer)',
            self::STATUS_RESOLVED_SELLER => 'Resolved (Seller)',
            self::STATUS_CLOSED => 'Closed',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }
}
