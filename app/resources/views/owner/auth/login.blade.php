@extends('layouts.owner')

@section('title', 'Masuk owner')

@section('content')
    <section class="owner-auth-page" aria-labelledby="owner-login-title">
        <div class="owner-auth-card">
            <p class="section-eyebrow">AKSES TERBATAS</p>
            <h1 id="owner-login-title">Masuk ke console toko</h1>
            <p class="auth-intro">Gunakan akun store owner yang telah disiapkan untuk AntaMart.</p>
            <form class="auth-form" method="post" action="{{ route('owner.auth.login') }}">
                @csrf
                <x-form-field name="email" label="Email owner" type="email" required autocomplete="username" />
                <div class="form-field">
                    <label class="field-label" for="owner-password">Kata sandi <span class="required-mark" aria-hidden="true">*</span></label>
                    <input id="owner-password" name="password" class="field-input @error('password') field-input-invalid @enderror" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="owner-password-error" @enderror>
                    @error('password')<p class="field-error" id="owner-password-error">{{ $message }}</p>@enderror
                </div>
                <button class="button button-primary" type="submit">Masuk ke console</button>
            </form>
            <a class="auth-guest-link" href="{{ route('storefront.home') }}">Kembali ke storefront</a>
        </div>
    </section>
@endsection
