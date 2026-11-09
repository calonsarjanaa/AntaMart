@extends('layouts.owner')

@section('title', 'Tambah produk')

@section('breadcrumbs')
    <a href="{{ route('owner.dashboard') }}">Dashboard</a><span aria-hidden="true">/</span>
    <a href="{{ route('owner.products.index') }}">Katalog</a><span aria-hidden="true">/</span><span>Tambah produk</span>
@endsection

@section('content')
    <section aria-labelledby="owner-product-create-title">
        <div class="owner-page-heading"><div><p class="section-eyebrow">KATALOG TOKO</p><h1 id="owner-product-create-title">Tambah produk</h1></div></div>
        <form class="owner-product-form" method="post" action="{{ route('owner.products.store') }}" enctype="multipart/form-data">
            @csrf
            <section class="owner-panel" aria-labelledby="new-product-info-title">
                <h2 id="new-product-info-title">Informasi produk</h2>
                <div class="owner-product-form-grid">
                    <div class="form-field"><label class="field-label" for="name">Nama produk <span class="required-mark" aria-hidden="true">*</span></label><input class="field-input" id="name" name="name" value="{{ old('name') }}" maxlength="255" required></div>
                    <div class="form-field"><label class="field-label" for="category_id">Kategori <span class="required-mark" aria-hidden="true">*</span></label><select class="field-input" id="category_id" name="category_id" required><option value="">Pilih kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="form-field owner-product-description"><label class="field-label" for="description">Deskripsi</label><textarea class="field-input" id="description" name="description" rows="5" maxlength="10000">{{ old('description') }}</textarea></div>
                    <div class="form-field owner-product-description"><label class="field-label" for="image">Foto produk <span>(opsional, maks. 5 MB)</span></label><input class="field-input" id="image" name="image" type="file" accept="image/*"><small>Gunakan gambar yang dapat dipakai di storefront.</small></div>
                </div>
            </section>
            <section class="owner-panel" aria-labelledby="new-product-variant-title">
                <h2 id="new-product-variant-title">Varian pertama</h2>
                <div class="owner-product-form-grid">
                    <div class="form-field"><label class="field-label" for="variant_name">Nama varian <span class="required-mark" aria-hidden="true">*</span></label><input class="field-input" id="variant_name" name="variant_name" value="{{ old('variant_name', 'Default') }}" maxlength="255" required></div>
                    <div class="form-field"><label class="field-label" for="sku">SKU <span class="required-mark" aria-hidden="true">*</span></label><input class="field-input" id="sku" name="sku" value="{{ old('sku') }}" maxlength="100" required></div>
                    <div class="form-field"><label class="field-label" for="price">Harga (Rp) <span class="required-mark" aria-hidden="true">*</span></label><input class="field-input" id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', '0.00') }}" required></div>
                    <div class="form-field"><label class="field-label" for="stock_physical">Stok fisik <span class="required-mark" aria-hidden="true">*</span></label><input class="field-input" id="stock_physical" name="stock_physical" type="number" min="0" step="1" value="{{ old('stock_physical', 0) }}" required></div>
                    <div class="form-field"><label class="field-label" for="is_active">Status varian</label><select class="field-input" id="is_active" name="is_active"><option value="1" @selected(old('is_active', '1') === '1')>Aktif</option><option value="0" @selected(old('is_active') === '0')>Nonaktif</option></select></div>
                </div>
            </section>
            <div class="owner-product-form-actions"><a class="button button-secondary" href="{{ route('owner.products.index') }}">Batal</a><button class="button button-dark" type="submit">Simpan produk</button></div>
        </form>
    </section>
@endsection
