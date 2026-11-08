@extends('layouts.app')

@section('title', 'Keranjang belanja')

@section('content')
    <section class="cart-page page-container" aria-labelledby="cart-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">ANTAMART</p><h1 id="cart-title">Keranjang</h1></div>
            <span class="result-count">{{ $items->sum('quantity') }} item</span>
        </div>

        @if ($items->isEmpty())
            <x-empty-state class="cart-empty" title="Keranjang masih kosong" description="Pilih produk yang kamu butuhkan, lalu tambahkan ke keranjang.">
                <x-slot:footer><a class="button button-primary" href="{{ route('storefront.home') }}#products">Lihat produk</a></x-slot:footer>
            </x-empty-state>
        @else
            <div class="cart-layout">
                <div class="cart-items">
                    @foreach ($items as $item)
                        <article class="cart-item">
                            @if ($item['product_slug'])
                                <a class="cart-item-media" href="{{ route('storefront.products.show', $item['product_slug']) }}" aria-label="Lihat {{ $item['product_name'] }}">
                            @else
                                <span class="cart-item-media" aria-hidden="true">
                            @endif
                                @if ($item['image_url'])
                                    <img src="{{ $item['image_url'] }}" alt="">
                                @else
                                    <span aria-hidden="true">□</span>
                                @endif
                            @if ($item['product_slug'])</a>@else</span>@endif
                            <div class="cart-item-info">
                                <div class="cart-item-heading">
                                    <div>
                                        <h2>{{ $item['product_name'] }}</h2>
                                        @if ($item['variant_name'])<p>{{ $item['variant_name'] }}</p>@endif
                                    </div>
                                    <form method="post" action="{{ route('storefront.cart.items.destroy', $item['variant_id']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="remove-link" type="submit">Hapus</button>
                                    </form>
                                </div>
                                @if ($item['purchasable'])
                                    <div class="cart-item-bottom">
                                        <form class="cart-quantity-form" method="post" action="{{ route('storefront.cart.items.update', $item['variant_id']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label class="visually-hidden" for="quantity-{{ $item['variant_id'] }}">Jumlah {{ $item['product_name'] }}</label>
                                            <input id="quantity-{{ $item['variant_id'] }}" type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" max="{{ $item['available_stock'] }}" required>
                                            <button class="quantity-update" type="submit">Perbarui</button>
                                        </form>
                                        <div class="cart-item-cost">
                                            <span><x-price :amount="$item['price']" /> / item</span>
                                            <strong><x-price :amount="$item['line_total']" /></strong>
                                        </div>
                                    </div>
                                @else
                                    <x-alert tone="warning" class="cart-item-warning">
                                        @if ($item['available_stock'] > 0)
                                            Stok tersisa {{ $item['available_stock'] }}. Perbarui jumlah atau hapus produk ini.
                                        @else
                                            Produk ini sedang tidak tersedia. Hapus dari keranjang untuk melanjutkan.
                                        @endif
                                    </x-alert>
                                    @if ($item['available_stock'] > 0)
                                        <form class="cart-quantity-form" method="post" action="{{ route('storefront.cart.items.update', $item['variant_id']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label class="visually-hidden" for="quantity-{{ $item['variant_id'] }}">Jumlah {{ $item['product_name'] }}</label>
                                            <input id="quantity-{{ $item['variant_id'] }}" type="number" name="quantity" value="{{ min($item['quantity'], $item['available_stock']) }}" min="1" max="{{ $item['available_stock'] }}" required>
                                            <button class="quantity-update" type="submit">Gunakan stok tersedia</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <aside class="cart-summary" aria-labelledby="summary-title">
                    <h2 id="summary-title">Ringkasan</h2>
                    <div class="summary-row"><span>Subtotal produk tersedia</span><strong><x-price :amount="$subtotal" /></strong></div>
                    <p>Ongkos kirim dihitung saat checkout.</p>
                    @if ($hasUnavailableItems)
                        <x-alert tone="warning" class="summary-warning">Perbarui atau hapus produk yang stoknya berubah sebelum melanjutkan.</x-alert>
                    @endif
                    @if ($hasUnavailableItems)
                        <button class="button button-disabled" type="button" disabled>Lanjut ke checkout</button>
                        <small>Perbarui keranjang untuk melanjutkan.</small>
                    @else
                        <a class="button button-primary" href="{{ route('storefront.checkout.create') }}">Lanjut ke checkout</a>
                    @endif
                    <a class="continue-shopping" href="{{ route('storefront.home') }}#products">← Lanjut belanja</a>
                </aside>
            </div>
        @endif
    </section>
@endsection
