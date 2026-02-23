<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'query_params',
        'last_run_at',
    ];

    protected $casts = [
        'query_params' => 'array',
        'last_run_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Build the URL to re-run this saved search.
     */
    public function getSearchUrl(): string
    {
        $params = $this->query_params ?? [];

        return route('auctions.index', $params);
    }
}
