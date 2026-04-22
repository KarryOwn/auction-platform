<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSellerSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_date',
        'active_listings',
        'completed_sales',
        'gross_revenue',
        'avg_sale_price',
        'avg_rating',
        'total_bids_received',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'active_listings' => 'integer',
            'completed_sales' => 'integer',
            'gross_revenue' => 'decimal:2',
            'avg_sale_price' => 'decimal:2',
            'avg_rating' => 'decimal:2',
            'total_bids_received' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
