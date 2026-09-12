<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;

class ProductSearchService
{
    public function __construct(
        protected SearchService $searchService,
    ) {
    }

    /**
     * @return array<int, array{id: int, name: string, category: ?string, brand: ?string, image_url: ?string, lowest_price: int, lowest_price_formatted: string, highest_price: int, domains_count: int, prices: array}>
     */
    public function search(string $query, string $sort = 'price_asc', int $limit = 20): array
    {
        $normalized = $this->searchService->normalizePersian($query);
        $booleanQuery = $this->searchService->prepareQuery($normalized);

        if ($booleanQuery === '') {
            return [];
        }

        $products = Product::search($booleanQuery)
            ->with(['prices' => fn ($q) => $q->orderBy('price')->with('domain')])
            ->limit($limit)
            ->get()
            ->filter(fn (Product $product) => $product->prices->isNotEmpty())
            ->values();

        $items = $products->map(fn (Product $product) => $this->toArray($product))->values();

        $direction = $sort === 'price_desc' ? -1 : 1;

        return $items->sortBy(fn ($item) => $item['lowest_price'] * $direction)->values()->all();
    }

    public function pricesForProduct(int $productId): array
    {
        return ProductPrice::where('product_id', $productId)
            ->with('domain')
            ->orderBy('price')
            ->get()
            ->map(fn (ProductPrice $price) => $this->priceToArray($price))
            ->all();
    }

    protected function toArray(Product $product): array
    {
        $prices = $product->prices;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
            'brand' => $product->brand,
            'image_url' => $product->image_url,
            'lowest_price' => (int) $prices->min('price'),
            'lowest_price_formatted' => $prices->sortBy('price')->first()->price_formatted,
            'highest_price' => (int) $prices->max('price'),
            'domains_count' => $prices->pluck('domain_id')->unique()->count(),
            'prices' => $prices->map(fn (ProductPrice $price) => $this->priceToArray($price))->all(),
        ];
    }

    protected function priceToArray(ProductPrice $price): array
    {
        return [
            'domain' => $price->domain?->name,
            'price' => (int) $price->price,
            'price_formatted' => $price->price_formatted,
            'availability' => $price->availability,
            'product_url' => $price->product_url,
        ];
    }
}
