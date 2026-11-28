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
                    <x-indonesia-address-fields
                        prefix="shipping_address"
                        :required="true"
                        :values="['recipient_name' => $customer?->name]"
                    />

                    <div class="checkout-toggle-wrapper" style="display: grid; gap: 10px;">
                        @if ($customer)
                            <div>
                                <label class="toggle-switch" for="toggle-profile-address">
                                    <input
                                        type="checkbox"
                                        id="toggle-profile-address"
                                        role="switch"
                                        aria-checked="false"
                                        class="toggle-switch-input"
                                        data-profile-address='@json($profileAddress)'
                                    >
                                    <span class="toggle-switch-slider" aria-hidden="true"></span>
                                    <span class="toggle-switch-label">Gunakan alamat sesuai profil</span>
                                </label>
                                <p class="toggle-switch-hint" id="toggle-profile-address-hint" style="display:none;"></p>
                            </div>
                        @endif
                        <div>
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
                    </div>
                </section>

                <section class="checkout-form-section" aria-labelledby="payment-method-title">
                    <h2 id="payment-method-title">Metode pembayaran</h2>
                    <fieldset class="payment-method-options" aria-describedby="payment-method-help">
                        <legend class="visually-hidden">Pilih metode pembayaran</legend>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="xendit" required @checked(old('payment_method', 'xendit') === 'xendit')>
                            <span><strong>Bayar online (QRIS)</strong><small>Pembayaran instan via QRIS melalui Xendit.</small></span>
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
            const addressGroup = document.querySelector('[data-indonesia-address]');
            if (!toggle || !form || !addressGroup) return;

            let backupValues = null;

            form.addEventListener('submit', function () {
                try {
                    const addr = window.AntaMartAddress.snapshot(addressGroup);
                    const dataToSave = {
                        guest_email: document.getElementById('guest_email')?.value || '',
                        guest_phone: document.getElementById('guest_phone')?.value || '',
                        ...addr,
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
                }
            }

            toggle.addEventListener('change', async function () {
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
                        ...window.AntaMartAddress.snapshot(addressGroup),
                    };

                    if (previousData.guest_email) setFieldValue('guest_email', previousData.guest_email);
                    if (previousData.guest_phone) setFieldValue('guest_phone', previousData.guest_phone);
                    await window.AntaMartAddress.apply(addressGroup, previousData);

                    if (hint) {
                        hint.textContent = 'Informasi checkout sebelumnya berhasil diterapkan.';
                        hint.className = 'toggle-switch-hint success';
                        hint.style.display = 'block';
                    }
                } else {
                    if (backupValues) {
                        setFieldValue('guest_email', backupValues.guest_email);
                        setFieldValue('guest_phone', backupValues.guest_phone);
                        await window.AntaMartAddress.apply(addressGroup, backupValues);
                    }
                    if (hint) hint.style.display = 'none';
                }
            });

            const profileToggle = document.getElementById('toggle-profile-address');
            const profileHint = document.getElementById('toggle-profile-address-hint');
            let profileBackupValues = null;

            if (profileToggle) {
                profileToggle.addEventListener('change', async function () {
                    profileToggle.setAttribute('aria-checked', profileToggle.checked ? 'true' : 'false');

                    if (profileToggle.checked) {
                        let profileData = null;
                        const raw = profileToggle.getAttribute('data-profile-address');
                        if (raw && raw !== 'null' && raw !== '') {
                            try {
                                const parsed = JSON.parse(raw);
                                if (parsed && typeof parsed === 'object' && Object.keys(parsed).length > 0) {
                                    profileData = parsed;
                                }
                            } catch (e) {}
                        }

                        if (!profileData) {
                            profileToggle.checked = false;
                            profileToggle.setAttribute('aria-checked', 'false');
                            if (profileHint) {
                                profileHint.innerHTML = 'Alamat pada profil belum diisi. Silakan lengkapi di <a href="{{ route('storefront.account.profile.show') }}" style="text-decoration: underline;">halaman profil</a>.';
                                profileHint.className = 'toggle-switch-hint warning';
                                profileHint.style.display = 'block';
                            }
                            return;
                        }

                        if (toggle.checked) {
                            toggle.checked = false;
                            toggle.setAttribute('aria-checked', 'false');
                            if (hint) hint.style.display = 'none';
                        }

                        profileBackupValues = window.AntaMartAddress.snapshot(addressGroup);
                        await window.AntaMartAddress.apply(addressGroup, profileData);

                        if (profileHint) {
                            profileHint.textContent = 'Alamat dari profil berhasil diterapkan.';
                            profileHint.className = 'toggle-switch-hint success';
                            profileHint.style.display = 'block';
                        }
                    } else {
                        if (profileBackupValues) {
                            await window.AntaMartAddress.apply(addressGroup, profileBackupValues);
                        }
                        if (profileHint) profileHint.style.display = 'none';
                    }
                });
            }
        });
    </script>
@endsection
