<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'page_id',
        'domain_id',
        'price',
        'price_formatted',
        'currency',
        'seller_name',
        'availability',
        'product_url',
        'extracted_at',
    ];

    protected $casts = [
        'price' => 'integer',
        'extracted_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }
}
