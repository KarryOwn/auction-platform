<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $fillable = [
        'referrer_id', 'referee_id', 'referrer_reward',
        'referee_reward', 'status', 'credited_at',
    ];
    protected $casts = [
        'referrer_reward' => 'decimal:2',
        'referee_reward'  => 'decimal:2',
        'credited_at'     => 'datetime',
    ];

    public function referrer(): BelongsTo { return $this->belongsTo(User::class, 'referrer_id'); }
    public function referee(): BelongsTo  { return $this->belongsTo(User::class, 'referee_id'); }
}
