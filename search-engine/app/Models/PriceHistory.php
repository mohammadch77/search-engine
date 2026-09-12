<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'price_history';

    protected $fillable = [
        'product_price_id',
        'price',
        'recorded_at',
    ];

    protected $casts = [
        'price' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function productPrice(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class);
    }
}
