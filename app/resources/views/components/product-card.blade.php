@props(['product', 'href' => '#'])

@php
    $variants = collect(data_get($product, 'variants', []));
    $startingPrice = $variants->pluck('price')->filter(fn ($price) => $price !== null)->min();
    $hasStock = $variants->contains(fn ($variant) => (bool) data_get($variant, 'in_stock'));
    $imageUrl = data_get($product, 'image_url');
@endphp

<article {{ $attributes->class(['product-card']) }}>
    <a class="product-card-link" href="{{ $href }}" aria-label="Lihat {{ data_get($product, 'name') }}">
        <div class="product-card-media">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ data_get($product, 'name') }}" loading="lazy">
            @else
                <span class="product-card-placeholder" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none"><path d="m5 10 11-6 11 6v12l-11 6-11-6V10Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="m5.5 10 10.5 6 10.5-6M16 16v12M10 7l11 6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                </span>
            @endif
            <span class="product-stock-label {{ $hasStock ? 'stock-available' : 'stock-unavailable' }}">
                {{ $hasStock ? 'Tersedia' : 'Stok habis' }}
            </span>
        </div>
        <div class="product-card-content">
            <div class="product-card-heading">
                <h3>{{ data_get($product, 'name') }}</h3>
                @if ($startingPrice !== null)
                    <x-price :amount="$startingPrice" />
                @endif
            </div>
            @if (data_get($product, 'description'))
                <p>{{ data_get($product, 'description') }}</p>
            @endif
        </div>
    </a>
</article>
