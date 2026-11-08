@extends('layouts.app')

@section('title', 'Terlalu banyak permintaan')

@section('content')
    <section class="tracking-page page-container" aria-labelledby="rate-limit-title">
        <div class="checkout-result-panel">
            <p class="section-eyebrow">ANTAMART</p>
            <h1 id="rate-limit-title">Tunggu sebentar sebelum mencoba lagi</h1>
            <p>Terlalu banyak permintaan dikirim dalam waktu singkat. Coba lagi dalam satu menit.</p>
            <a class="button button-primary" href="{{ route('storefront.home') }}">Kembali ke beranda</a>
        </div>
    </section>
@endsection
