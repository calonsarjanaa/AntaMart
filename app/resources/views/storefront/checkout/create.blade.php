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
                        :show-map="true"
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

                <section
                    class="checkout-form-section"
                    id="shipping-options-section"
                    aria-labelledby="shipping-method-title"
                    data-subtotal="{{ (float) $subtotal }}"
                    data-weight-grams="{{ (int) ($totalWeightGrams ?? 500) }}"
                    data-old-shipping-option="{{ old('shipping_option', '') }}"
                >
                    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                        <h2 id="shipping-method-title">Pilihan pengiriman</h2>
                        <small style="color: #6b7280;">Estimasi berat: {{ number_format(($totalWeightGrams ?? 500) / 1000, 2, ',', '.') }} kg</small>
                    </div>
                    <p class="checkout-section-copy" id="shipping-options-status">
                        Pilih Provinsi, Kota/Kabupaten, Kecamatan, atau pasang pin di peta untuk melihat tarif Kurir AntaMart &amp; ekspedisi.
                    </p>
                    <div id="shipping-options-list" class="payment-method-options" style="margin-top: 8px;"></div>
                    @error('shipping_option')<p class="field-error">{{ $message }}</p>@enderror
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
                <div class="summary-row">
                    <span id="summary-shipping-label">Ongkos kirim</span>
                    <strong id="summary-shipping-cost">Rp 0</strong>
                </div>
                <div class="summary-row" style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 6px; font-size: 1.05em;">
                    <span>Total bayar</span>
                    <strong id="summary-grand-total"><x-price :amount="$subtotal" /></strong>
                </div>
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

            // Live Hybrid Shipping Rate Loader (Kurir AntaMart + RajaOngkir)
            const shippingSection = document.getElementById('shipping-options-section');
            const shippingList = document.getElementById('shipping-options-list');
            const shippingStatus = document.getElementById('shipping-options-status');
            const summaryShippingLabel = document.getElementById('summary-shipping-label');
            const summaryShippingCost = document.getElementById('summary-shipping-cost');
            const summaryGrandTotal = document.getElementById('summary-grand-total');

            if (shippingSection && shippingList && shippingStatus) {
                const subtotal = parseFloat(shippingSection.dataset.subtotal || '0');
                const weightGrams = parseInt(shippingSection.dataset.weightGrams || '500', 10);
                let selectedCode = shippingSection.dataset.oldShippingOption || '';
                let fetchTimer = null;
                let fetchSeq = 0;

                function formatIdr(amount) {
                    return 'Rp ' + Math.round(amount).toLocaleString('id-ID');
                }

                function updateSummary(option) {
                    const cost = option ? parseFloat(option.cost || 0) : 0;
                    if (summaryShippingLabel) {
                        summaryShippingLabel.textContent = option
                            ? 'Ongkir (' + option.courier_name + ' ' + option.service + ')'
                            : 'Ongkos kirim';
                    }
                    if (summaryShippingCost) summaryShippingCost.textContent = formatIdr(cost);
                    if (summaryGrandTotal) summaryGrandTotal.textContent = formatIdr(subtotal + cost);
                }

                function renderShippingOptions(data) {
                    shippingList.innerHTML = '';
                    const options = Array.isArray(data?.options) ? data.options : [];

                    if (options.length === 0) {
                        updateSummary(null);
                        if (data?.distance_km !== null && data?.distance_km > 20) {
                            shippingStatus.textContent = 'Lokasi berada di luar jangkauan Kurir AntaMart (' + Number(data.distance_km).toFixed(1) + ' km) dan tarif ekspedisi belum ditemukan untuk kecamatan ini. Coba pilih kecamatan/kota lain.';
                        } else {
                            shippingStatus.textContent = 'Belum ada tarif pengiriman ditemukan. Pastikan Kecamatan dipilih atau pasang pin lokasi pada peta.';
                        }
                        return;
                    }

                    const distInfo = (data.distance_km !== null && data.distance_km !== undefined)
                        ? 'Jarak dari toko: ' + Number(data.distance_km).toFixed(1) + ' km. '
                        : '';
                    shippingStatus.textContent = distInfo + 'Pilih salah satu metode pengiriman di bawah ini:';

                    let matched = options.find((o) => o.code === selectedCode);
                    if (!matched) {
                        matched = options[0];
                        selectedCode = matched.code;
                    }

                    options.forEach(function (opt) {
                        const label = document.createElement('label');
                        label.className = 'payment-method-option';
                        label.style.justifyContent = 'space-between';
                        label.style.alignItems = 'center';

                        const left = document.createElement('div');
                        left.style.display = 'flex';
                        left.style.alignItems = 'flex-start';
                        left.style.gap = '10px';

                        const radio = document.createElement('input');
                        radio.type = 'radio';
                        radio.name = 'shipping_option';
                        radio.value = opt.code;
                        radio.required = true;
                        radio.checked = opt.code === selectedCode;
                        radio.addEventListener('change', function () {
                            selectedCode = opt.code;
                            updateSummary(opt);
                        });

                        const textWrap = document.createElement('span');
                        const badge = opt.type === 'local'
                            ? ' <span style="display:inline-block;padding:1px 6px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:0.75rem;font-weight:600;">Kurir Toko</span>'
                            : '';
                        textWrap.innerHTML = '<strong>' + opt.courier_name + ' — ' + opt.service + '</strong>' + badge
                            + '<small style="display:block;color:#6b7280;">' + (opt.description || '') + (opt.etd ? ' · Estimasi: ' + opt.etd : '') + '</small>';

                        left.appendChild(radio);
                        left.appendChild(textWrap);

                        const priceEl = document.createElement('strong');
                        priceEl.style.whiteSpace = 'nowrap';
                        priceEl.textContent = formatIdr(opt.cost);

                        label.appendChild(left);
                        label.appendChild(priceEl);
                        shippingList.appendChild(label);
                    });

                    updateSummary(matched);
                }

                async function loadShippingRates() {
                    const snap = window.AntaMartAddress.snapshot(addressGroup);
                    const hasRegion = Boolean(snap.city || snap.district);
                    const hasCoords = Boolean(snap.latitude && snap.longitude);

                    if (!hasRegion && !hasCoords) {
                        shippingList.innerHTML = '';
                        shippingStatus.textContent = 'Pilih Provinsi, Kota/Kabupaten, Kecamatan, atau pasang pin di peta untuk melihat tarif Kurir AntaMart & ekspedisi.';
                        updateSummary(null);
                        return;
                    }

                    const seq = ++fetchSeq;
                    shippingStatus.textContent = 'Menghitung ongkos kirim...';

                    try {
                        const res = await fetch('/api/shipping/rates', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                province: snap.province || null,
                                city: snap.city || null,
                                district: snap.district || null,
                                village: snap.village || null,
                                latitude: snap.latitude ? parseFloat(snap.latitude) : null,
                                longitude: snap.longitude ? parseFloat(snap.longitude) : null,
                                weight_grams: weightGrams,
                            }),
                        });

                        if (seq !== fetchSeq) return;
                        if (!res.ok) {
                            shippingStatus.textContent = 'Gagal memuat tarif pengiriman. Silakan coba lagi.';
                            return;
                        }

                        const data = await res.json();
                        renderShippingOptions(data);
                    } catch (e) {
                        if (seq === fetchSeq) {
                            shippingStatus.textContent = 'Koneksi bermasalah saat memuat tarif pengiriman.';
                        }
                    }
                }

                addressGroup.addEventListener('antamart:address-changed', function () {
                    clearTimeout(fetchTimer);
                    fetchTimer = setTimeout(loadShippingRates, 250);
                });
            }
        });
    </script>
@endsection
