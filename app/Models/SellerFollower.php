<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SellerFollower extends Pivot
{
    protected $table = 'seller_followers';

    protected $casts = [
        'notify_new_listings' => 'boolean',
    ];
}
