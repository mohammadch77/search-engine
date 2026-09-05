<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'url',
        'url_hash',
        'title',
        'meta_description',
        'meta_keywords',
        'content_raw',
        'content_text',
        'content_hash',
        'http_status',
        'content_type',
        'language',
        'word_count',
        'depth',
        'page_rank',
        'status',
        'crawled_at',
        'indexed_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'word_count' => 'integer',
        'depth' => 'integer',
        'page_rank' => 'float',
        'crawled_at' => 'datetime',
        'indexed_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class, 'source_page_id');
    }

    public function scopeIndexed(Builder $query): Builder
    {
        return $query->where('status', 'indexed');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query
            ->selectRaw('pages.*, MATCH(title, content_text) AGAINST (? IN BOOLEAN MODE) AS relevance', [$term])
            ->whereRaw('MATCH(title, content_text) AGAINST (? IN BOOLEAN MODE)', [$term])
            ->orderByDesc('relevance');
    }
}
