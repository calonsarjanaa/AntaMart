<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OwnerProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $search = trim($filters['q'] ?? '');

        $products = Product::query()
            ->withCount('variants')
            ->with(['category', 'variants' => fn ($query) => $query->orderBy('id')])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'like', "%{$search}%"));
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('owner.products.index', compact('products', 'search'));
    }

    public function create(): View
    {
        return view('owner.products.create', [
            'categories' => ProductCategory::query()->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'variant_name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'stock_physical' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ]);

        $imagePath = $request->file('image')?->store('products', 'public');
        $product = DB::transaction(function () use ($data, $imagePath): Product {
            $baseSlug = Str::slug($data['name']) ?: 'product';
            $slug = $baseSlug;
            for ($suffix = 2; Product::where('slug', $slug)->exists(); $suffix++) {
                $slug = "{$baseSlug}-{$suffix}";
            }

            $product = Product::create([
                'name' => $data['name'],
                'slug' => $slug,
                'category_id' => $data['category_id'],
                'description' => $data['description'] ?? null,
                'image_path' => $imagePath,
                'is_active' => true,
            ]);
            $product->variants()->create([
                'sku' => $data['sku'],
                'name' => $data['variant_name'],
                'price' => $data['price'],
                'stock_physical' => $data['stock_physical'],
                'stock_reserved' => 0,
                'is_active' => (bool) $data['is_active'],
            ]);

            return $product;
        });

        return redirect()->route('owner.products.edit', $product)->with('status', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        $product->load(['variants' => fn ($query) => $query->orderBy('id')]);

        return view('owner.products.edit', [
            'product' => $product,
            'categories' => ProductCategory::query()->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'is_active' => ['required', 'boolean'],
        ]);

        $oldImagePath = $product->image_path;
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }
        $product->update([
            'name' => $data['name'],
            'category_id' => $data['category_id'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'image_path' => $data['image_path'] ?? $oldImagePath,
        ]);
        if (isset($data['image_path']) && $oldImagePath && $oldImagePath !== $data['image_path']) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return back()->with('status', 'Informasi produk berhasil diperbarui.');
    }

    public function storeVariant(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'stock_physical' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ]);
        $product->variants()->create([
            ...$data,
            'stock_reserved' => 0,
            'is_active' => (bool) $data['is_active'],
        ]);

        return back()->with('status', 'Varian berhasil ditambahkan.');
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant, AuditService $audit): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variant->id)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'stock_physical' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $variant, $request, $audit): void {
            $lockedVariant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            if ((int) $data['stock_physical'] < $lockedVariant->stock_reserved) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'stock_physical' => "Stok fisik tidak boleh di bawah {$lockedVariant->stock_reserved} unit yang sudah dipesan.",
                ]);
            }

            $oldStock = $lockedVariant->stock_physical;
            $lockedVariant->update([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'price' => $data['price'],
                'stock_physical' => $data['stock_physical'],
                'is_active' => (bool) $data['is_active'],
            ]);
            if ((int) $data['stock_physical'] !== (int) $oldStock) {
                $audit->record('inventory.adjusted', null, $request->user()->id, 'store_owner', [
                    'product_id' => $lockedVariant->product_id,
                    'product_variant_id' => $lockedVariant->id,
                    'sku' => $lockedVariant->sku,
                    'old_stock_physical' => $oldStock,
                    'new_stock_physical' => (int) $data['stock_physical'],
                    'stock_reserved' => $lockedVariant->stock_reserved,
                ]);
            }
        });

        return back()->with('status', 'Varian dan stok berhasil diperbarui.');
    }
}
