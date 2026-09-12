<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->withCount('prices')
            ->orderByDesc('prices_count')
            ->paginate(25);

        return Inertia::render('Admin/Products', [
            'products' => $products,
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load(['prices' => fn ($q) => $q->orderBy('price')->with('domain')]);

        return Inertia::render('Admin/ProductDetail', [
            'product' => $product,
        ]);
    }
}
