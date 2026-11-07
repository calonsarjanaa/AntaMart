@extends('layouts.app')

@section('title', $product['name'])

@section('content')
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
                        <div class="variant-option {{ $variant['in_stock'] ? '' : 'variant-sold-out' }}">
                            <div><strong>{{ $variant['name'] }}</strong><small>SKU {{ $variant['sku'] }}</small></div>
                            <div class="variant-option-meta">
                                <x-price :amount="$variant['price']" />
                                <x-status-badge :status="$variant['in_stock'] ? 'Tersedia' : 'Habis'" />
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="detail-note">Ketersediaan stok diperiksa kembali saat checkout.</p>
            </div>
        </article>
    </div>
@endsection
