@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
    <section class="auth-page page-container" aria-labelledby="login-title">
        <div class="auth-card">
            <p class="section-eyebrow">ANTAMART</p>
            <h1 id="login-title">Masuk ke akun</h1>
            <p class="auth-intro">Masuk untuk menggunakan akun pelanggan AntaMart.</p>
            <form class="auth-form" method="post" action="{{ route('storefront.auth.login') }}">
                @csrf
                <x-form-field name="email" label="Email" type="email" required autocomplete="email" />
                <div class="form-field">
                    <label class="field-label" for="password">Kata sandi <span class="required-mark" aria-hidden="true">*</span></label>
                    <input id="password" name="password" class="field-input @error('password') field-input-invalid @enderror" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
                </div>
                <button class="button button-primary" type="submit">Masuk</button>
            </form>
            <p class="auth-switch">Belum punya akun? <a href="{{ route('register') }}">Daftar sebagai pelanggan</a></p>
            <a class="auth-guest-link" href="{{ route('storefront.home') }}">Kembali ke belanja sebagai tamu</a>
        </div>
    </section>
@endsection
