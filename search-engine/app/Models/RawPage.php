<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RawPage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'domain_id',
        'url',
        'url_hash',
        'depth',
        'html_content',
        'http_status',
        'content_type',
        'headers',
        'fetched_at',
        'processed',
        'locked_by',
        'attempts',
    ];

    protected $casts = [
        'depth' => 'integer',
        'http_status' => 'integer',
        'fetched_at' => 'datetime',
        'processed' => 'boolean',
        'attempts' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('processed', false)->whereNull('locked_by');
    }
}
