@extends('layouts.app')

@section('title', 'Daftar')

@section('content')
    <section class="auth-page page-container" aria-labelledby="register-title">
        <div class="auth-card" style="width: min(100%, 580px);">
            <p class="section-eyebrow">ANTAMART</p>
            <h1 id="register-title">Buat akun pelanggan</h1>
            <p class="auth-intro">Daftar untuk menyiapkan akun pelanggan. Kamu tetap bisa belanja tanpa akun.</p>
            <form class="auth-form" method="post" action="{{ route('storefront.auth.register') }}">
                @csrf
                <x-form-field name="name" label="Nama" required autocomplete="name" />
                <x-form-field name="email" label="Email" type="email" required autocomplete="email" />
                <div class="form-field">
                    <label class="field-label" for="password">Kata sandi <span class="required-mark" aria-hidden="true">*</span></label>
                    <input id="password" name="password" class="field-input @error('password') field-input-invalid @enderror" type="password" autocomplete="new-password" minlength="8" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    <small class="auth-field-hint">Gunakan minimal 8 karakter.</small>
                    @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-field">
                    <label class="field-label" for="password_confirmation">Ulangi kata sandi <span class="required-mark" aria-hidden="true">*</span></label>
                    <input id="password_confirmation" name="password_confirmation" class="field-input" type="password" autocomplete="new-password" minlength="8" required>
                </div>

                <div style="margin-top: 8px; padding-top: 14px; border-top: 1px dashed #e2e8f0;">
                    <h2 style="margin: 0 0 4px; font-size: 15px; color: #344054;">Data Pribadi &amp; Alamat <span style="font-weight: 400; color: #667085;">(Opsional)</span></h2>
                    <p style="margin: 0 0 12px; font-size: 13px; color: #667085;">Isi sekarang untuk verifikasi usia otomatis (21+) dan pengisian alamat cepat saat checkout, atau lewati untuk diisi nanti pada halaman profil.</p>
                    <div class="form-field" style="margin-bottom: 12px;">
                        <label class="field-label" for="birth_date">Tanggal lahir</label>
                        <input id="birth_date" name="birth_date" type="date" max="{{ now()->format('Y-m-d') }}" value="{{ old('birth_date') }}" class="field-input @error('birth_date') field-input-invalid @enderror">
                        @error('birth_date')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <x-indonesia-address-fields :required="false" />
                </div>

                <button class="button button-primary" type="submit">Buat akun</button>
                <button class="button button-secondary" type="submit" name="skip_profile" value="1">Skip — isi pada halaman profil</button>
            </form>
            <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
            <a class="auth-guest-link" href="{{ route('storefront.home') }}">Kembali ke belanja sebagai tamu</a>
        </div>
    </section>
@endsection
