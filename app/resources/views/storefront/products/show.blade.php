@extends('layouts.app')

@section('title', $product['name'])

@section('content')
    @php($firstAvailableVariantId = collect($product['variants'])->firstWhere('in_stock', true)['id'] ?? null)
    <div class="page-container product-detail-page">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ route('storefront.home') }}">Beranda</a><span aria-hidden="true">/</span><span>{{ $product['name'] }}</span>
        </nav>
        <article class="product-detail">
            <div class="product-detail-media">
                @if ($product['image_url'])
                    <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}">
                @else
                    <span class="product-card-placeholder" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none"><path d="m5 10 11-6 11 6v12l-11 6-11-6V10Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="m5.5 10 10.5 6 10.5-6M16 16v12M10 7l11 6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                    </span>
                @endif
            </div>
            <div class="product-detail-info">
                <p class="section-eyebrow">ANTAMART</p>
                <h1>{{ $product['name'] }}</h1>
                @php($startingPrice = collect($product['variants'])->pluck('price')->min())
                @if ($startingPrice !== null)
                    <p class="detail-price"><x-price :amount="$startingPrice" /></p>
                @endif
                @if ($product['description'])
                    <p class="product-description">{{ $product['description'] }}</p>
                @endif
                <div class="variant-list">
                    <h2>Pilihan produk</h2>
                    @foreach ($product['variants'] as $variant)
                        <label class="variant-option {{ $variant['in_stock'] ? '' : 'variant-sold-out' }}">
                            <span class="variant-option-choice">
                                @if ($variant['in_stock'])
                                    <input type="radio" name="product_variant_id" value="{{ $variant['id'] }}" form="add-to-cart-form" required @checked(old('product_variant_id') == $variant['id'] || (!old('product_variant_id') && $variant['id'] === $firstAvailableVariantId))>
                                @else
                                    <input type="radio" disabled aria-label="{{ $variant['name'] }} habis">
                                @endif
                            </span>
                            <span><strong>{{ $variant['name'] }}</strong><small>SKU {{ $variant['sku'] }}</small></span>
                            <span class="variant-option-meta">
                                <x-price :amount="$variant['price']" />
                                <x-status-badge :status="$variant['in_stock'] ? 'Tersedia' : 'Habis'" />
                            </span>
                        </label>
                    @endforeach
                </div>
                @if (collect($product['variants'])->contains('in_stock', true))
                    <form id="add-to-cart-form" class="add-to-cart-form" method="post" action="{{ route('storefront.cart.items.store') }}">
                        @csrf
                        <label class="quantity-field" for="quantity">Jumlah
                            <input id="quantity" type="number" name="quantity" min="1" max="1000" value="{{ old('quantity', 1) }}" required>
                        </label>
                        <button class="button button-primary" type="submit">Tambah ke keranjang</button>
                    </form>
                @else
                    <button class="button button-disabled" type="button" disabled>Stok habis</button>
                @endif
                <p class="detail-note">Ketersediaan stok diperiksa kembali saat checkout.</p>
            </div>
        </article>
    </div>
@endsection
