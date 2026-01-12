<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    use HasFactory;

    // ADD THIS BLOCK
    protected $fillable = [
        'auction_id',
        'user_id',
        'amount',
        'ip_address',
        'user_agent',
    ];
}
