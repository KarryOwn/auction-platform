<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    public const DELIVERY_PENDING = 'pending';
    public const DELIVERY_PREPARING = 'preparing';
    public const DELIVERY_SHIPPED = 'shipped';
    public const DELIVERY_DELIVERED = 'delivered';
    public const DELIVERY_CANCELLED = 'cancelled';

    protected $fillable = [
        'auction_id',
        'buyer_id',
        'seller_id',
        'last_message_at',
        'buyer_read_at',
        'seller_read_at',
        'is_closed',
        'delivery_status',
        'delivery_updated_at',
        'delivery_note',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'buyer_read_at' => 'datetime',
        'seller_read_at' => 'datetime',
        'is_closed' => 'boolean',
        'delivery_updated_at' => 'datetime',
    ];

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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function canParticipate(User $user): bool
    {
        return $user->id === $this->buyer_id || $user->id === $this->seller_id;
    }

    public static function deliveryStatuses(): array
    {
        return [
            self::DELIVERY_PENDING => 'Pending',
            self::DELIVERY_PREPARING => 'Preparing',
            self::DELIVERY_SHIPPED => 'Shipped',
            self::DELIVERY_DELIVERED => 'Delivered',
            self::DELIVERY_CANCELLED => 'Cancelled',
        ];
    }

    public function getDeliveryStatusLabelAttribute(): ?string
    {
        return self::deliveryStatuses()[$this->delivery_status] ?? null;
    }
}
