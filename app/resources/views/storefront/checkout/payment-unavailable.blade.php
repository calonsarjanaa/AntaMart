@extends('layouts.app')

@section('title', 'Lanjutkan pembayaran')

@section('content')
    <section class="checkout-page page-container" aria-labelledby="payment-retry-title">
        <div class="checkout-result-panel">
            <p class="section-eyebrow">PESANAN {{ $order->id }}</p>
            <h1 id="payment-retry-title">Pesanan tersimpan, pembayaran belum siap</h1>
            <p>Pesananmu sudah dibuat dan stok telah dicadangkan. Kami belum menerima tautan pembayaran, jadi pesanan masih menunggu pembayaran.</p>
            <div class="checkout-result-total">
                <span>Total pesanan</span><strong><x-price :amount="$order->total" /></strong>
            </div>
            <form method="post" action="{{ route('storefront.checkout.payment.retry') }}">
                @csrf
                <button class="button button-primary" type="submit">Coba buat tautan pembayaran lagi</button>
            </form>
            <p class="checkout-result-note">Jangan membuat pesanan baru. Gunakan tombol di atas untuk mencoba lagi pada pesanan yang sama.</p>
        </div>
    </section>
@endsection
