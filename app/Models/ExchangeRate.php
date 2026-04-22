<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }
}
