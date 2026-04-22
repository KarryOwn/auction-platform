<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsHourlyBidVolume extends Model
{
    use HasFactory;

    protected $table = 'analytics_hourly_bid_volume';

    protected $fillable = [
        'report_date',
        'hour_of_day',
        'day_of_week',
        'bid_count',
        'unique_bidders',
        'unique_auctions',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'hour_of_day' => 'integer',
            'bid_count' => 'integer',
            'unique_bidders' => 'integer',
            'unique_auctions' => 'integer',
        ];
    }
}
