@extends('layouts.owner')

@section('title', 'Katalog produk')

@section('breadcrumbs')
    <a href="{{ route('owner.dashboard') }}">Dashboard</a><span aria-hidden="true">/</span><span>Katalog</span>
@endsection

@section('content')
    <section aria-labelledby="owner-products-title">
        <div class="owner-page-heading">
            <div><p class="section-eyebrow">KATALOG TOKO</p><h1 id="owner-products-title">Produk</h1></div>
            <a class="button button-dark" href="{{ route('owner.products.create') }}">Tambah produk</a>
        </div>

        <form class="owner-order-filters" method="get" action="{{ route('owner.products.index') }}">
            <div class="form-field owner-search-field">
                <label class="field-label" for="product-search">Cari produk atau SKU</label>
                <input id="product-search" class="field-input" type="search" name="q" value="{{ $search }}" placeholder="Nama produk atau SKU">
            </div>
            <div class="owner-filter-actions">
                <button class="button button-primary" type="submit">Cari</button>
                <a class="button button-secondary" href="{{ route('owner.products.index') }}">Reset</a>
            </div>
        </form>

        @if ($products->isEmpty())
            <div class="owner-empty-panel">
                <h2>{{ $search !== '' ? 'Produk tidak ditemukan' : 'Belum ada produk' }}</h2>
                <p>{{ $search !== '' ? 'Coba kata kunci lain.' : 'Tambahkan produk agar dapat ditampilkan di storefront.' }}</p>
                @if ($search === '')<p><a class="owner-table-action" href="{{ route('owner.products.create') }}">Tambah produk pertama</a></p>@endif
            </div>
        @else
            <div class="owner-table-wrap">
                <table class="owner-table owner-products-table">
                    <thead><tr><th>Produk</th><th>Kategori</th><th>Status</th><th>Varian</th><th>Stok tersedia</th><th><span class="visually-hidden">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td><span class="owner-buyer-name">{{ $product->name }}</span><small>/products/{{ $product->slug }}</small></td>
                                <td>{{ $product->category?->name ?? 'Belum diatur' }}</td>
                                <td><span class="owner-product-status {{ $product->is_active ? 'is-active' : '' }}">{{ $product->is_active ? 'Aktif' : 'Diarsipkan' }}</span></td>
                                <td>{{ $product->variants_count }}</td>
                                <td>{{ $product->variants->sum(fn ($variant) => $variant->is_active ? $variant->available_stock : 0) }}</td>
                                <td><a class="owner-table-action" href="{{ route('owner.products.edit', $product) }}">Kelola</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <nav class="pagination" aria-label="Navigasi daftar produk">
                    @if ($products->onFirstPage())<span class="pagination-disabled">Sebelumnya</span>@else<a href="{{ $products->previousPageUrl() }}">Sebelumnya</a>@endif
                    <span>Halaman {{ $products->currentPage() }} dari {{ $products->lastPage() }}</span>
                    @if ($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}">Berikutnya</a>@else<span class="pagination-disabled">Berikutnya</span>@endif
                </nav>
            @endif
        @endif
    </section>
@endsection
