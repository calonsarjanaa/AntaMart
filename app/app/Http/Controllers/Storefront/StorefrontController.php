<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorefrontController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'availability' => ['nullable', 'in:all,in-stock,sold-out'],
            'category' => ['nullable', 'string', 'exists:product_categories,slug'],
        ]);

        $query = Product::query()
            ->where('is_active', true)
            ->whereHas('variants', fn ($variants) => $variants->where('is_active', true));

        if (!empty($filters['q'])) {
            $term = trim($filters['q']);
            $query->where(function ($products) use ($term): void {
                $products->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if (!empty($filters['category'])) {
            $query->whereHas('category', fn ($category) => $category->where('slug', $filters['category']));
        }

        if (($filters['availability'] ?? 'all') === 'in-stock') {
            $query->whereHas('variants', fn ($variants) => $variants
                ->where('is_active', true)
                ->whereColumn('stock_physical', '>', 'stock_reserved'));
        } elseif (($filters['availability'] ?? null) === 'sold-out') {
            $query->whereDoesntHave('variants', fn ($variants) => $variants
                ->where('is_active', true)
                ->whereColumn('stock_physical', '>', 'stock_reserved'));
        }

        $products = $query
            ->with(['variants' => fn ($variants) => $variants->where('is_active', true)->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $products->getCollection()->transform(fn (Product $product): array => $this->presentProduct($product));

        return view('storefront.home', [
            'products' => $products,
            'search' => $filters['q'] ?? '',
            'availability' => $filters['availability'] ?? 'all',
            'categories' => ProductCategory::query()->orderBy('id')->get(),
            'categoryFilter' => $filters['category'] ?? '',
            'selectedCategory' => ProductCategory::query()->where('slug', $filters['category'] ?? '')->first(),
        ]);
    }

    public function show(string $slug)
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('variants', fn ($variants) => $variants->where('is_active', true))
            ->with(['variants' => fn ($variants) => $variants->where('is_active', true)->orderBy('name')])
            ->firstOrFail();

        return view('storefront.products.show', [
            'product' => $this->presentProduct($product),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentProduct(Product $product): array
    {
        $variants = $product->variants->map(fn ($variant): array => [
            'id' => $variant->id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'price' => $variant->price,
            'available_stock' => $variant->available_stock,
            'in_stock' => $variant->available_stock > 0,
        ])->values();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
            'variants' => $variants,
        ];
    }
}
