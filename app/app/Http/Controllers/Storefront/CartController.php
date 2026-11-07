<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        $variants = ProductVariant::query()
            ->whereIn('id', array_keys($cart))
            ->with('product')
            ->get()
            ->keyBy('id');

        $items = collect($cart)->map(function (int $quantity, int|string $id) use ($variants): array {
            $variant = $variants->get($id);
            $product = $variant?->product;

            if (!$variant || !$variant->is_active || !$product?->is_active) {
                return [
                    'variant_id' => $id,
                    'quantity' => $quantity,
                    'product_name' => 'Produk tidak tersedia',
                    'product_slug' => null,
                    'variant_name' => null,
                    'sku' => null,
                    'image_url' => null,
                    'price' => null,
                    'available_stock' => 0,
                    'line_total' => null,
                    'purchasable' => false,
                ];
            }

            $availableStock = $variant->available_stock;
            $purchasable = $availableStock > 0 && $quantity <= $availableStock;

            return [
                'variant_id' => $variant->id,
                'quantity' => $quantity,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
                'price' => $variant->price,
                'available_stock' => $availableStock,
                'line_total' => $variant->price * $quantity,
                'purchasable' => $purchasable,
            ];
        })->values();

        return view('storefront.cart.index', [
            'items' => $items,
            'subtotal' => $items->where('purchasable', true)->sum('line_total'),
            'hasUnavailableItems' => $items->contains(fn (array $item): bool => !$item['purchasable']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $variant = ProductVariant::query()
            ->whereKey($data['product_variant_id'])
            ->where('is_active', true)
            ->whereHas('product', fn ($product) => $product->where('is_active', true))
            ->with('product')
            ->first();

        if (!$variant) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Produk ini sudah tidak tersedia.',
            ]);
        }

        $cart = session('cart', []);
        $currentQuantity = (int) ($cart[$variant->id] ?? 0);
        $newQuantity = $currentQuantity + (int) $data['quantity'];

        if ($newQuantity > $variant->available_stock) {
            throw ValidationException::withMessages([
                'quantity' => "Stok tersedia untuk {$variant->name} adalah {$variant->available_stock}.",
            ]);
        }

        $cart[$variant->id] = $newQuantity;
        session()->put('cart', $cart);

        return redirect()->route('storefront.cart.index')->with('status', 'Produk ditambahkan ke keranjang.');
    }

    public function update(Request $request, int $variantId)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $cart = session('cart', []);
        abort_unless(array_key_exists($variantId, $cart), 404);

        $variant = ProductVariant::query()
            ->whereKey($variantId)
            ->where('is_active', true)
            ->whereHas('product', fn ($product) => $product->where('is_active', true))
            ->first();

        if (!$variant || (int) $data['quantity'] > $variant->available_stock) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah melebihi stok yang tersedia. Hapus produk ini jika sudah tidak dibutuhkan.',
            ]);
        }

        $cart[$variantId] = (int) $data['quantity'];
        session()->put('cart', $cart);

        return redirect()->route('storefront.cart.index')->with('status', 'Jumlah produk diperbarui.');
    }

    public function destroy(int $variantId)
    {
        $cart = session('cart', []);
        unset($cart[$variantId]);
        session()->put('cart', $cart);

        return redirect()->route('storefront.cart.index')->with('status', 'Produk dihapus dari keranjang.');
    }
}
