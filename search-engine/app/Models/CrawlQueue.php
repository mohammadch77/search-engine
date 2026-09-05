<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CrawlQueue extends Model
{
    use HasFactory;

    protected $table = 'crawl_queue';

    protected $fillable = [
        'domain_id',
        'url',
        'url_hash',
        'priority',
        'depth',
        'status',
        'attempts',
        'max_attempts',
        'last_attempt_at',
    ];

    protected $casts = [
        'priority' => 'integer',
        'depth' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'last_attempt_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeNextByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderBy('id');
    }
}
