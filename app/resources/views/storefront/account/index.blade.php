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
                <a class="button button-secondary" href="{{ route('storefront.account.profile.show') }}">Profil &amp; Alamat</a>
                <a class="button button-secondary" href="{{ route('storefront.account.orders.index') }}">Riwayat pesanan</a>
                <a class="button button-primary" href="{{ route('storefront.home') }}">Lanjut belanja</a>
                <form method="post" action="{{ route('storefront.account.logout') }}">
                    @csrf
                    <button class="button button-secondary" type="submit">Keluar</button>
                </form>
            </div>
        </div>

        <div class="checkout-form-section" style="margin-top: 16px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px;">
                <h2 style="margin: 0;">Ringkasan profil</h2>
                <a class="text-link" href="{{ route('storefront.account.profile.show') }}" style="color: var(--blue); font-weight: 600;">Ubah profil</a>
            </div>
            <div style="display: grid; gap: 8px; color: #475467;">
                <div>
                    <strong>Tanggal lahir:</strong>
                    @if ($user->birth_date)
                        {{ $user->birth_date->format('d M Y') }} ({{ $user->birth_date->age }} tahun)
                    @else
                        <span style="color: #98a2b3;">Belum diisi — lengkapi untuk verifikasi usia otomatis.</span>
                    @endif
                </div>
                <div>
                    <strong>Alamat pengiriman:</strong>
                    @if ($user->address_line1 || $user->province)
                        {{ $user->recipient_name ?: $user->name }} ({{ $user->phone ?: '-' }}) · {{ implode(', ', array_filter([$user->address_line1, $user->village ? 'Desa/Kel. ' . $user->village : null, $user->district ? 'Kec. ' . $user->district : null, $user->city, $user->province])) }}
                    @else
                        <span style="color: #98a2b3;">Belum diisi — lengkapi agar bisa ditarik otomatis saat checkout.</span>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
