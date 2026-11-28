@extends('layouts.app')

@section('title', 'Profil pelanggan')

@section('content')
    <section class="account-page page-container" aria-labelledby="profile-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">AKUN PELANGGAN</p><h1 id="profile-title">Profil &amp; Alamat</h1></div>
            <a class="continue-shopping" href="{{ route('storefront.account.index') }}">← Kembali ke akun</a>
        </div>

        <form class="checkout-form-panel" method="post" action="{{ route('storefront.account.profile.update') }}" style="max-width: 760px;">
            @csrf
            @method('PUT')

            <section class="checkout-form-section" aria-labelledby="profile-personal-title">
                <h2 id="profile-personal-title">Data pribadi</h2>
                <p class="checkout-section-copy">Tanggal lahir digunakan untuk verifikasi usia otomatis pada kategori khusus dewasa (21+).</p>
                <div class="checkout-fields">
                    <x-form-field name="name" label="Nama lengkap" :value="$user->name" autocomplete="name" required />
                    <div class="form-field">
                        <label class="field-label" for="birth_date">Tanggal lahir</label>
                        <input
                            id="birth_date"
                            name="birth_date"
                            type="date"
                            max="{{ now()->format('Y-m-d') }}"
                            value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"
                            class="field-input @error('birth_date') field-input-invalid @enderror"
                        >
                        @error('birth_date')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="checkout-form-section" aria-labelledby="profile-address-title">
                <h2 id="profile-address-title">Alamat pengiriman default</h2>
                <p class="checkout-section-copy">Alamat ini dapat langsung ditarik saat checkout menggunakan tombol <strong>Gunakan alamat sesuai profil</strong>.</p>
                <x-indonesia-address-fields
                    :required="false"
                    :values="[
                        'recipient_name' => $user->recipient_name ?: $user->name,
                        'phone' => $user->phone,
                        'province' => $user->province,
                        'city' => $user->city,
                        'district' => $user->district,
                        'village' => $user->village,
                        'address_line1' => $user->address_line1,
                    ]"
                />
            </section>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <a class="button button-secondary" href="{{ route('storefront.account.index') }}">Batal</a>
                <button class="button button-primary" type="submit">Simpan profil</button>
            </div>
        </form>
    </section>
@endsection

