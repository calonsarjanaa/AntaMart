@extends('layouts.app')

@section('title', 'Akun pelanggan')

@section('content')
    <section class="account-page page-container" aria-labelledby="account-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">ANTAMART</p><h1 id="account-title">Akun pelanggan</h1></div>
        </div>
        <div class="account-card">
            <div class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
            <div class="account-details">
                <h2>{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
                <small>Akun dibuat {{ $user->created_at?->timezone(config('app.timezone'))->format('d M Y') }}</small>
            </div>
            <div class="account-actions">
                <a class="button button-secondary" href="{{ route('storefront.account.orders.index') }}">Riwayat pesanan</a>
                <a class="button button-primary" href="{{ route('storefront.home') }}">Lanjut belanja</a>
                <form method="post" action="{{ route('storefront.account.logout') }}">
                    @csrf
                    <button class="button button-secondary" type="submit">Keluar</button>
                </form>
            </div>
        </div>
    </section>
@endsection
