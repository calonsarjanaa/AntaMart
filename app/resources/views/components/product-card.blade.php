@props(['product', 'href' => '#'])

@php
    $variants = collect(data_get($product, 'variants', []));
    $startingPrice = $variants->pluck('price')->filter(fn ($price) => $price !== null)->min();
    $hasStock = $variants->contains(fn ($variant) => (bool) data_get($variant, 'in_stock'));
    $imageUrl = data_get($product, 'image_url');
@endphp

<article {{ $attributes->class(['product-card']) }}>
    <a class="product-card-link" href="{{ $href }}" aria-label="View {{ data_get($product, 'name') }}">
        <div class="product-card-media">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ data_get($product, 'name') }}" loading="lazy">
            @else
                <span class="product-card-placeholder" aria-hidden="true"><i></i><i></i><i></i></span>
            @endif
            <span class="product-stock-label {{ $hasStock ? 'stock-available' : 'stock-unavailable' }}">
                {{ $hasStock ? 'In stock' : 'Sold out' }}
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
