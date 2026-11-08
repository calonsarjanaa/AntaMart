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
                @if (!$customer)
                    <section class="checkout-form-section" aria-labelledby="contact-title">
                        <h2 id="contact-title">Kontak pembeli</h2>
                        <p class="checkout-section-copy">Informasi ini digunakan untuk konfirmasi pesanan.</p>
                        <div class="checkout-fields">
                            <x-form-field name="guest_email" label="Email" type="email" autocomplete="email" required />
                            <x-form-field name="guest_phone" label="Nomor telepon" type="tel" autocomplete="tel" required />
                        </div>
                    </section>
                @else
                    <section class="checkout-form-section" aria-labelledby="contact-title">
                        <h2 id="contact-title">Kontak akun</h2>
                        <p class="checkout-section-copy">Konfirmasi pesanan akan menggunakan {{ $customer->email }}.</p>
                    </section>
                @endif

                <section class="checkout-form-section" aria-labelledby="address-title">
                    <h2 id="address-title">Alamat pengiriman</h2>
                    <div class="checkout-fields">
                        <x-form-field name="shipping_address[recipient_name]" label="Nama penerima" :value="$customer?->name" autocomplete="name" required />
                        <x-form-field name="shipping_address[phone]" label="Nomor telepon penerima" type="tel" autocomplete="shipping tel" required />
                        <x-form-field name="shipping_address[address_line1]" label="Alamat" autocomplete="shipping street-address" required />
                        <x-form-field name="shipping_address[address_line2]" label="Detail alamat (opsional)" autocomplete="shipping address-line2" />
                        <x-form-field name="shipping_address[city]" label="Kota/Kabupaten" autocomplete="shipping address-level2" required />
                        <x-form-field name="shipping_address[province]" label="Provinsi" autocomplete="shipping address-level1" required />
                        <x-form-field name="shipping_address[postal_code]" label="Kode pos" autocomplete="shipping postal-code" required />
                    </div>
                </section>

                <section class="checkout-form-section" aria-labelledby="payment-method-title">
                    <h2 id="payment-method-title">Metode pembayaran</h2>
                    <fieldset class="payment-method-options" aria-describedby="payment-method-help">
                        <legend class="visually-hidden">Pilih metode pembayaran</legend>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="xendit" required @checked(old('payment_method', 'xendit') === 'xendit')>
                            <span><strong>Bayar online</strong><small>Pembayaran aman melalui Xendit.</small></span>
                        </label>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="cod" required @checked(old('payment_method') === 'cod')>
                            <span><strong>Bayar di tempat (COD)</strong><small>Bayar tunai saat pesanan diterima.</small></span>
                        </label>
                        <small id="payment-method-help">Total pembayaran termasuk ongkos kirim yang dihitung saat pesanan dibuat.</small>
                    </fieldset>
                    @error('payment_method')<p class="field-error" id="payment-method-error">{{ $message }}</p>@enderror
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
