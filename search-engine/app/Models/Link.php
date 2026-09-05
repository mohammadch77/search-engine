<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'source_page_id',
        'target_page_id',
        'target_url',
        'anchor_text',
        'is_external',
    ];

    protected $casts = [
        'is_external' => 'boolean',
    ];

    public function sourcePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'source_page_id');
    }

    public function targetPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'target_page_id');
    }
}
