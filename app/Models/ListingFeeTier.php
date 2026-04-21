<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingFeeTier extends Model
{
    protected $fillable = [
        'name', 'starting_price_min', 'starting_price_max',
        'category_id', 'fee_amount', 'fee_percent', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'starting_price_min' => 'decimal:2',
        'starting_price_max' => 'decimal:2',
        'fee_amount'         => 'decimal:2',
        'fee_percent'        => 'decimal:4',
        'is_active'          => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
