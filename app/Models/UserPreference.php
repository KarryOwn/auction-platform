<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'theme',
        'locale',
        'bid_increment_preference',
        'custom_increment_amount',
        'notification_email',
        'notification_push',
        'notification_database',
        'show_bid_history_names',
        'watchlist_email_digest',
        'timezone',
    ];

    protected $casts = [
        'notification_email'      => 'array',
        'notification_push'       => 'array',
        'notification_database'   => 'array',
        'show_bid_history_names'  => 'boolean',
        'custom_increment_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'notification_email'    => [],
                'notification_push'     => [],
                'notification_database' => [],
            ]
        );
    }
}