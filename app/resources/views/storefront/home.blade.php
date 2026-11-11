@extends('layouts.app')

@section('title', 'Belanja kebutuhan sehari-hari')

@section('content')
    <section class="home-hero" aria-labelledby="hero-title">
        <div class="hero-copy">
            <p class="hero-kicker">PILIHAN UNTUK HARI-HARI</p>
            <h1 id="hero-title">Belanja kebutuhan<br>jadi lebih mudah.</h1>
            <p>Temukan produk untuk kebutuhan sehari-hari di AntaMart.</p>
            <a class="button button-white" href="#products">Belanja sekarang <span aria-hidden="true">›</span></a>
        </div>
    </section>

    <section class="service-strip" aria-label="Informasi layanan AntaMart">
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 19 6v5c0 4.4-2.8 7.8-7 10-4.2-2.2-7-5.6-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.5"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span><strong>Checkout sebagai tamu</strong><small>Belanja tanpa membuat akun</small></span>
        </article>
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 10h8M8 14h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </span>
            <span><strong>Pembayaran via Xendit</strong><small>Status pembayaran diperbarui</small></span>
        </article>
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.5"/><path d="m16 16 4 4M8 11h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </span>
            <span><strong>Lacak pesanan</strong><small>Gunakan ID pesanan dan email</small></span>
        </article>
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v13H4zM8 7V4h8v3M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span><strong>Stok diperiksa saat checkout</strong><small>Ketersediaan dikonfirmasi kembali</small></span>
        </article>
    </section>

    <section class="catalog-section" id="products" aria-labelledby="catalog-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">ANTAMART</p><h2 id="catalog-title">{{ $selectedCategory?->name ?? 'Produk' }}</h2></div>
            <span class="result-count">{{ $products->total() }} produk</span>
        </div>

        <nav class="filter-chips" aria-label="Filter ketersediaan produk">
            @foreach (['all' => 'Semua', 'in-stock' => 'Tersedia', 'sold-out' => 'Habis'] as $filter => $label)
                <a class="filter-chip {{ $availability === $filter ? 'is-active' : '' }}"
                   href="{{ route('storefront.home', array_filter(['q' => $search, 'availability' => $filter === 'all' ? null : $filter, 'category' => $categoryFilter ?: null])) }}"
                   @if ($availability === $filter) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <nav class="filter-chips category-filter-chips" aria-label="Filter kategori produk">
            <a class="filter-chip {{ $categoryFilter === '' ? 'is-active' : '' }}"
               href="{{ route('storefront.home', array_filter(['q' => $search, 'availability' => $availability === 'all' ? null : $availability])) }}"
               @if ($categoryFilter === '') aria-current="page" @endif>Semua kategori</a>
            @foreach ($categories as $category)
                <a class="filter-chip {{ $categoryFilter === $category->slug ? 'is-active' : '' }} {{ $category->isAgeRestricted() ? 'chip-age-restricted' : '' }}"
                   href="{{ route('storefront.home', array_filter(['q' => $search, 'availability' => $availability === 'all' ? null : $availability, 'category' => $category->slug])) }}"
                   @if ($categoryFilter === $category->slug) aria-current="page" @endif>
                    {{ $category->name }}
                    @if ($category->isAgeRestricted())
                        <span class="badge-age-limit" aria-label="Batas usia {{ $category->min_age }} tahun">{{ $category->min_age }}+</span>
                    @endif
                </a>
            @endforeach
        </nav>

        @if ($products->count())
            <div class="product-grid">
                @foreach ($products as $product)
                    <x-product-card :product="$product" :href="route('storefront.products.show', $product['slug'])" />
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav class="pagination" aria-label="Navigasi halaman produk">
                    @if ($products->onFirstPage())
                        <span class="pagination-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}">Sebelumnya</a>
                    @endif
                    <span>Halaman {{ $products->currentPage() }} dari {{ $products->lastPage() }}</span>
                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}">Berikutnya</a>
                    @else
                        <span class="pagination-disabled">Berikutnya</span>
                    @endif
                </nav>
            @endif
        @else
            <x-empty-state
                class="catalog-empty"
                :title="$search ? 'Produk tidak ditemukan' : ($selectedCategory ? 'Belum ada produk di kategori ini' : 'Belum ada produk untuk ditampilkan')"
                :description="$search ? 'Coba kata pencarian lain.' : ($selectedCategory ? 'Belum ada produk aktif di kategori ini.' : 'Silakan kembali lagi nanti.')"
            />
        @endif
    </section>
@endsection
