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

                    <div class="checkout-toggle-wrapper">
                        <label class="toggle-switch" for="toggle-previous-checkout">
                            <input
                                type="checkbox"
                                id="toggle-previous-checkout"
                                role="switch"
                                aria-checked="false"
                                class="toggle-switch-input"
                                data-server-checkout='@json($lastCheckout)'
                            >
                            <span class="toggle-switch-slider" aria-hidden="true"></span>
                            <span class="toggle-switch-label">Gunakan informasi checkout sebelumnya</span>
                        </label>
                        <p class="toggle-switch-hint" id="toggle-previous-checkout-hint" style="display:none;"></p>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('toggle-previous-checkout');
            const hint = document.getElementById('toggle-previous-checkout-hint');
            const form = document.querySelector('.checkout-layout');
            if (!toggle || !form) return;

            let backupValues = null;

            form.addEventListener('submit', function () {
                try {
                    const dataToSave = {
                        guest_email: document.getElementById('guest_email')?.value || '',
                        guest_phone: document.getElementById('guest_phone')?.value || '',
                        recipient_name: document.getElementById('shipping_address_recipient_name')?.value || '',
                        phone: document.getElementById('shipping_address_phone')?.value || '',
                        address_line1: document.getElementById('shipping_address_address_line1')?.value || '',
                        address_line2: document.getElementById('shipping_address_address_line2')?.value || '',
                        city: document.getElementById('shipping_address_city')?.value || '',
                        province: document.getElementById('shipping_address_province')?.value || '',
                        postal_code: document.getElementById('shipping_address_postal_code')?.value || '',
                    };
                    localStorage.setItem('antamart_last_checkout', JSON.stringify(dataToSave));
                } catch (e) {}
            });

            function getPreviousCheckoutData() {
                const serverDataRaw = toggle.getAttribute('data-server-checkout');
                if (serverDataRaw && serverDataRaw !== 'null' && serverDataRaw !== '') {
                    try {
                        const parsed = JSON.parse(serverDataRaw);
                        if (parsed && typeof parsed === 'object' && Object.keys(parsed).length > 0) {
                            return parsed;
                        }
                    } catch (e) {}
                }

                try {
                    const localDataRaw = localStorage.getItem('antamart_last_checkout');
                    if (localDataRaw) {
                        const parsed = JSON.parse(localDataRaw);
                        if (parsed && typeof parsed === 'object' && Object.keys(parsed).length > 0) {
                            return parsed;
                        }
                    }
                } catch (e) {}

                return null;
            }

            function setFieldValue(id, value) {
                const el = document.getElementById(id);
                if (el && value !== undefined && value !== null) {
                    el.value = value;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            toggle.addEventListener('change', function () {
                toggle.setAttribute('aria-checked', toggle.checked ? 'true' : 'false');

                if (toggle.checked) {
                    const previousData = getPreviousCheckoutData();

                    if (!previousData) {
                        toggle.checked = false;
                        toggle.setAttribute('aria-checked', 'false');
                        if (hint) {
                            hint.textContent = 'Belum ada informasi checkout sebelumnya yang tersimpan.';
                            hint.className = 'toggle-switch-hint warning';
                            hint.style.display = 'block';
                            setTimeout(function () { hint.style.display = 'none'; }, 4000);
                        }
                        return;
                    }

                    backupValues = {
                        guest_email: document.getElementById('guest_email')?.value || '',
                        guest_phone: document.getElementById('guest_phone')?.value || '',
                        recipient_name: document.getElementById('shipping_address_recipient_name')?.value || '',
                        phone: document.getElementById('shipping_address_phone')?.value || '',
                        address_line1: document.getElementById('shipping_address_address_line1')?.value || '',
                        address_line2: document.getElementById('shipping_address_address_line2')?.value || '',
                        city: document.getElementById('shipping_address_city')?.value || '',
                        province: document.getElementById('shipping_address_province')?.value || '',
                        postal_code: document.getElementById('shipping_address_postal_code')?.value || '',
                    };

                    if (previousData.recipient_name) setFieldValue('shipping_address_recipient_name', previousData.recipient_name);
                    if (previousData.phone) setFieldValue('shipping_address_phone', previousData.phone);
                    if (previousData.address_line1) setFieldValue('shipping_address_address_line1', previousData.address_line1);
                    if (previousData.address_line2 !== undefined) setFieldValue('shipping_address_address_line2', previousData.address_line2);
                    if (previousData.city) setFieldValue('shipping_address_city', previousData.city);
                    if (previousData.province) setFieldValue('shipping_address_province', previousData.province);
                    if (previousData.postal_code) setFieldValue('shipping_address_postal_code', previousData.postal_code);
                    if (previousData.guest_email) setFieldValue('guest_email', previousData.guest_email);
                    if (previousData.guest_phone) setFieldValue('guest_phone', previousData.guest_phone);

                    if (hint) {
                        hint.textContent = 'Informasi checkout sebelumnya berhasil diterapkan.';
                        hint.className = 'toggle-switch-hint success';
                        hint.style.display = 'block';
                    }
                } else {
                    if (backupValues) {
                        setFieldValue('shipping_address_recipient_name', backupValues.recipient_name);
                        setFieldValue('shipping_address_phone', backupValues.phone);
                        setFieldValue('shipping_address_address_line1', backupValues.address_line1);
                        setFieldValue('shipping_address_address_line2', backupValues.address_line2);
                        setFieldValue('shipping_address_city', backupValues.city);
                        setFieldValue('shipping_address_province', backupValues.province);
                        setFieldValue('shipping_address_postal_code', backupValues.postal_code);
                        setFieldValue('guest_email', backupValues.guest_email);
                        setFieldValue('guest_phone', backupValues.guest_phone);
                    }

                    if (hint) {
                        hint.style.display = 'none';
                    }
                }
            });
        });
    </script>
@endsection
