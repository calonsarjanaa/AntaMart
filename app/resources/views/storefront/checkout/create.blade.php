@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <section class="checkout-page page-container" aria-labelledby="checkout-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">ANTAMART</p><h1 id="checkout-title">Checkout</h1></div>
            <a class="continue-shopping" href="{{ route('storefront.cart.index') }}">← Kembali ke keranjang</a>
        </div>

        <form class="checkout-layout" method="post" action="{{ route('storefront.checkout.store') }}">
            @csrf
            <div class="checkout-form-panel">
                <section class="checkout-form-section" aria-labelledby="contact-title">
                    <h2 id="contact-title">Kontak pembeli</h2>
                    <p class="checkout-section-copy">Informasi ini digunakan untuk konfirmasi pesanan.</p>
                    <div class="checkout-fields">
                        <x-form-field name="guest_email" label="Email" type="email" autocomplete="email" required />
                        <x-form-field name="guest_phone" label="Nomor telepon" type="tel" autocomplete="tel" required />
                    </div>
                </section>

                <section class="checkout-form-section" aria-labelledby="address-title">
                    <h2 id="address-title">Alamat pengiriman</h2>
                    <div class="checkout-fields">
                        <x-form-field name="shipping_address[recipient_name]" label="Nama penerima" autocomplete="name" required />
                        <x-form-field name="shipping_address[phone]" label="Nomor telepon penerima" type="tel" autocomplete="shipping tel" required />
                        <x-form-field name="shipping_address[address_line1]" label="Alamat" autocomplete="shipping street-address" required />
                        <x-form-field name="shipping_address[address_line2]" label="Detail alamat (opsional)" autocomplete="shipping address-line2" />
                        <x-form-field name="shipping_address[city]" label="Kota/Kabupaten" autocomplete="shipping address-level2" required />
                        <x-form-field name="shipping_address[province]" label="Provinsi" autocomplete="shipping address-level1" required />
                        <x-form-field name="shipping_address[postal_code]" label="Kode pos" autocomplete="shipping postal-code" required />
                    </div>
                </section>
            </div>

            <aside class="cart-summary checkout-summary" aria-labelledby="checkout-summary-title">
                <h2 id="checkout-summary-title">Ringkasan pesanan</h2>
                <div class="checkout-summary-items">
                    @foreach ($items as $item)
                        <div class="checkout-summary-item">
                            <div>
                                <strong>{{ $item['product_name'] }}</strong>
                                @if ($item['variant_name'])<span>{{ $item['variant_name'] }} · {{ $item['quantity'] }} item</span>@else<span>{{ $item['quantity'] }} item</span>@endif
                            </div>
                            <strong><x-price :amount="$item['line_total']" /></strong>
                        </div>
                    @endforeach
                </div>
                <div class="summary-row"><span>Subtotal</span><strong><x-price :amount="$subtotal" /></strong></div>
                <p>Ongkos kirim dan total akhir dihitung setelah alamat diproses.</p>
                <button class="button button-primary" type="submit">Buat pesanan</button>
                <small>Harga dan ketersediaan stok akan diperiksa kembali saat pesanan dibuat.</small>
            </aside>
        </form>
    </section>
@endsection
