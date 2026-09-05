<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base_url',
        'robots_txt',
        'robots_checked',
        'crawl_delay_ms',
        'status',
        'max_depth',
        'pages_count',
        'last_crawled_at',
    ];

    protected $casts = [
        'robots_checked' => 'boolean',
        'crawl_delay_ms' => 'integer',
        'max_depth' => 'integer',
        'pages_count' => 'integer',
        'last_crawled_at' => 'datetime',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function crawlQueue(): HasMany
    {
        return $this->hasMany(CrawlQueue::class);
    }

    public function crawlLogs(): HasMany
    {
        return $this->hasMany(CrawlLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
