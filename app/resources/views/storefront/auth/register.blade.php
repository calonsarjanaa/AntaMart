@extends('layouts.app')

@section('title', 'Daftar')

@section('content')
    <section class="auth-page page-container" aria-labelledby="register-title">
        <div class="auth-card">
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
                <button class="button button-primary" type="submit">Buat akun</button>
            </form>
            <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
            <a class="auth-guest-link" href="{{ route('storefront.home') }}">Kembali ke belanja sebagai tamu</a>
        </div>
    </section>
@endsection
