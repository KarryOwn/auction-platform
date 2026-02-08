<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public static function record(
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?array $metadata = null,
        ?int $userId = null,
    ): static {
        return static::create([
            'user_id'     => $userId ?? auth()->id(),
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'metadata'    => $metadata,
            'ip_address'  => request()->ip(),
            'created_at'  => now(),
        ]);
    }
}
