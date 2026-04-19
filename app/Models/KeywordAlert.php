<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordAlert extends Model
{
    protected $fillable = [
        'user_id',
        'keyword',
        'is_active',
        'last_notified_at',
        'notify_email',
        'notify_database',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify_email' => 'boolean',
        'notify_database' => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function matchingAuction(Auction $auction): Builder
    {
        return self::whereRaw("? ILIKE '%' || keyword || '%'", [$auction->title]);
    }
}
