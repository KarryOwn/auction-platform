<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataExportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'file_path',
        'ready_at',
        'expires_at',
    ];

    protected $casts = [
        'ready_at'   => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
