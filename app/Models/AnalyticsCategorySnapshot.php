<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsCategorySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'report_date',
        'total_auctions',
        'completed_auctions',
        'cancelled_auctions',
        'sell_through_rate',
        'avg_final_price',
        'avg_starting_price',
        'price_appreciation_pct',
        'total_bids',
        'unique_bidders',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'sell_through_rate' => 'decimal:4',
            'avg_final_price' => 'decimal:2',
            'avg_starting_price' => 'decimal:2',
            'price_appreciation_pct' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
