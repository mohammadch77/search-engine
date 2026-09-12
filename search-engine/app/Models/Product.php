<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_normalized',
        'category',
        'brand',
        'image_url',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query
            ->selectRaw('products.*, MATCH(name, name_normalized) AGAINST (? IN BOOLEAN MODE) AS relevance', [$term])
            ->whereRaw('MATCH(name, name_normalized) AGAINST (? IN BOOLEAN MODE)', [$term])
            ->orderByDesc('relevance');
    }
}
