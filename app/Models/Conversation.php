<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'buyer_id',
        'seller_id',
        'last_message_at',
        'buyer_read_at',
        'seller_read_at',
        'is_closed',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'buyer_read_at' => 'datetime',
        'seller_read_at' => 'datetime',
        'is_closed' => 'boolean',
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
}
