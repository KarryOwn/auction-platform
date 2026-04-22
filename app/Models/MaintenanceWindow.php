<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWindow extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'scheduled_start',
        'scheduled_end',
        'message',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUpcomingWithinHours(int $hours): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->scheduled_start !== null
            && $this->scheduled_start->isFuture()
            && $this->scheduled_start->lessThanOrEqualTo(now()->addHours($hours));
    }
}
