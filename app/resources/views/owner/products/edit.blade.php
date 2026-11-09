@extends('layouts.owner')

@section('title', 'Kelola produk')

@section('breadcrumbs')
    <a href="{{ route('owner.dashboard') }}">Dashboard</a><span aria-hidden="true">/</span>
    <a href="{{ route('owner.products.index') }}">Katalog</a><span aria-hidden="true">/</span><span>{{ $product->name }}</span>
@endsection

@section('content')
    <section aria-labelledby="owner-product-edit-title">
        <div class="owner-page-heading">
            <div><p class="section-eyebrow">KELOLA KATALOG</p><h1 id="owner-product-edit-title">{{ $product->name }}</h1></div>
            <a class="button button-secondary" href="{{ route('storefront.products.show', $product->slug) }}">Lihat di toko</a>
        </div>

        <div class="owner-product-management">
            <form class="owner-panel owner-product-form" method="post" action="{{ route('owner.products.update', $product) }}" enctype="multipart/form-data">
                @csrf @method('put')
                <h2>Informasi produk</h2>
                @if ($product->image_path)<img class="owner-product-image-preview" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="Foto {{ $product->name }}">@endif
                <div class="owner-product-form-grid">
                    <div class="form-field owner-product-description"><label class="field-label" for="name">Nama produk</label><input class="field-input" id="name" name="name" value="{{ old('name', $product->name) }}" maxlength="255" required></div>
                    <div class="form-field"><label class="field-label" for="category_id">Kategori</label><select class="field-input" id="category_id" name="category_id" required><option value="">Pilih kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="form-field owner-product-description"><label class="field-label" for="description">Deskripsi</label><textarea class="field-input" id="description" name="description" rows="5" maxlength="10000">{{ old('description', $product->description) }}</textarea></div>
                    <div class="form-field owner-product-description"><label class="field-label" for="image">Ganti foto <span>(opsional, maks. 5 MB)</span></label><input class="field-input" id="image" name="image" type="file" accept="image/*"></div>
                    <div class="form-field"><label class="field-label" for="product_is_active">Status katalog</label><select class="field-input" id="product_is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $product->is_active) === '1')>Aktif di toko</option><option value="0" @selected((string) old('is_active', (int) $product->is_active) === '0')>Arsipkan dari toko</option></select></div>
                </div>
                <div class="owner-product-form-actions"><button class="button button-dark" type="submit">Simpan informasi</button></div>
            </form>

            <section class="owner-panel" aria-labelledby="product-variants-title">
                <div class="owner-panel-heading"><h2 id="product-variants-title">Varian dan stok</h2><span>{{ $product->variants->count() }} varian</span></div>
                <p class="owner-product-help">Stok tersedia = stok fisik dikurangi jumlah yang sudah dipesan. Varian yang dinonaktifkan tidak dapat dibeli.</p>
                @forelse ($product->variants as $variant)
                    <form class="owner-variant-form" method="post" action="{{ route('owner.products.variants.update', [$product, $variant]) }}">
                        @csrf @method('put')
                        <h3>{{ $variant->name }} <small>{{ $variant->is_active ? 'Aktif' : 'Nonaktif' }}</small></h3>
                        <div class="owner-product-form-grid">
                            <div class="form-field"><label class="field-label" for="variant-name-{{ $variant->id }}">Nama varian</label><input class="field-input" id="variant-name-{{ $variant->id }}" name="name" value="{{ old('name', $variant->name) }}" maxlength="255" required></div>
                            <div class="form-field"><label class="field-label" for="variant-sku-{{ $variant->id }}">SKU</label><input class="field-input" id="variant-sku-{{ $variant->id }}" name="sku" value="{{ old('sku', $variant->sku) }}" maxlength="100" required></div>
                            <div class="form-field"><label class="field-label" for="variant-price-{{ $variant->id }}">Harga (Rp)</label><input class="field-input" id="variant-price-{{ $variant->id }}" name="price" type="number" min="0" step="0.01" value="{{ old('price', $variant->price) }}" required></div>
                            <div class="form-field"><label class="field-label" for="variant-stock-{{ $variant->id }}">Stok fisik</label><input class="field-input" id="variant-stock-{{ $variant->id }}" name="stock_physical" type="number" min="{{ $variant->stock_reserved }}" step="1" value="{{ old('stock_physical', $variant->stock_physical) }}" required><small>{{ $variant->stock_reserved }} unit telah dipesan · {{ $variant->available_stock }} tersedia</small></div>
                            <div class="form-field"><label class="field-label" for="variant-active-{{ $variant->id }}">Status varian</label><select class="field-input" id="variant-active-{{ $variant->id }}" name="is_active"><option value="1" @selected((string) old('is_active', (int) $variant->is_active) === '1')>Aktif</option><option value="0" @selected((string) old('is_active', (int) $variant->is_active) === '0')>Nonaktif</option></select></div>
                        </div>
                        <div class="owner-product-form-actions"><button class="button button-secondary" type="submit">Simpan varian</button></div>
                    </form>
                @empty
                    <p class="owner-product-help">Belum ada varian.</p>
                @endforelse
            </section>

            <section class="owner-panel" aria-labelledby="add-variant-title">
                <h2 id="add-variant-title">Tambah varian</h2>
                <form class="owner-product-form" method="post" action="{{ route('owner.products.variants.store', $product) }}">
                    @csrf
                    <div class="owner-product-form-grid">
                        <div class="form-field"><label class="field-label" for="new-variant-name">Nama varian</label><input class="field-input" id="new-variant-name" name="name" value="{{ old('name') }}" maxlength="255" required></div>
                        <div class="form-field"><label class="field-label" for="new-variant-sku">SKU</label><input class="field-input" id="new-variant-sku" name="sku" value="{{ old('sku') }}" maxlength="100" required></div>
                        <div class="form-field"><label class="field-label" for="new-variant-price">Harga (Rp)</label><input class="field-input" id="new-variant-price" name="price" type="number" min="0" step="0.01" value="{{ old('price') }}" required></div>
                        <div class="form-field"><label class="field-label" for="new-variant-stock">Stok fisik</label><input class="field-input" id="new-variant-stock" name="stock_physical" type="number" min="0" step="1" value="{{ old('stock_physical', 0) }}" required></div>
                        <div class="form-field"><label class="field-label" for="new-variant-active">Status varian</label><select class="field-input" id="new-variant-active" name="is_active"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                    </div>
                    <div class="owner-product-form-actions"><button class="button button-dark" type="submit">Tambah varian</button></div>
                </form>
            </section>
        </div>
    </section>
@endsection
