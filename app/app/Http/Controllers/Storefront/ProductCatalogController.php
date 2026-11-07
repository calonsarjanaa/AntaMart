<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductCatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->whereHas('variants', fn ($query) => $query->where('is_active', true))
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        $data = $products->getCollection()->map(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
            'variants' => $product->variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'available_stock' => $variant->available_stock,
                'in_stock' => $variant->available_stock > 0,
            ])->values(),
        ])->values();

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
