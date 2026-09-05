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
        'locked_by',
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

    /**
     * Pending items, plus "processing" items whose worker died without
     * releasing the lock (stale for longer than the given number of minutes).
     */
    public function scopeClaimable(Builder $query, int $staleAfterMinutes = 10): Builder
    {
        return $query->where(function (Builder $q) use ($staleAfterMinutes) {
            $q->where('status', 'pending')
                ->orWhere(function (Builder $q2) use ($staleAfterMinutes) {
                    $q2->where('status', 'processing')
                        ->where('last_attempt_at', '<', now()->subMinutes($staleAfterMinutes));
                });
        });
    }

    public function scopeNextByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderBy('id');
    }
}
