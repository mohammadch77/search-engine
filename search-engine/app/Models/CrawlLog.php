<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;
    const CREATED_AT = null;

    protected $fillable = [
        'domain_id',
        'page_id',
        'url',
        'status_code',
        'response_time_ms',
        'content_size_bytes',
        'error_message',
        'crawled_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'content_size_bytes' => 'integer',
        'crawled_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
