<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period_label',
        'period_type',
        'period_start',
        'period_end',
        'file_path',
        'gross_sales',
        'platform_fees_paid',
        'listing_fees_paid',
        'net_revenue',
        'refunds_issued',
    ];

    protected $casts = [
        'period_start'       => 'date',
        'period_end'         => 'date',
        'gross_sales'        => 'decimal:2',
        'platform_fees_paid' => 'decimal:2',
        'listing_fees_paid'  => 'decimal:2',
        'net_revenue'        => 'decimal:2',
        'refunds_issued'     => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
