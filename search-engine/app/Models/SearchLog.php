<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;
    const CREATED_AT = null;

    protected $fillable = [
        'query',
        'results_count',
        'response_time_ms',
        'ip_address',
        'user_agent',
        'searched_at',
    ];

    protected $casts = [
        'results_count' => 'integer',
        'response_time_ms' => 'integer',
        'searched_at' => 'datetime',
    ];
}
