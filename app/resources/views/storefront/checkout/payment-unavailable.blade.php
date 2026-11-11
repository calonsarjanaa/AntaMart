@extends('layouts.app')

@section('title', 'Lanjutkan Pembayaran')

@section('content')
    <section class="checkout-page page-container" aria-labelledby="payment-retry-title">
        <div class="checkout-result-panel">
            <p class="section-eyebrow">PESANAN {{ $order->id }}</p>
            <h1 id="payment-retry-title">Pesanan Tersimpan, Pembayaran Belum Siap</h1>
            <p>Pesananmu sudah dibuat dan stok telah dicadangkan. Gateway pembayaran online (Xendit) belum siap atau sedang mengalami kendala, sehingga tautan pembayaran belum tersedia.</p>

            @if (!empty($errorMessage) || $errors->has('payment') || session('status'))
                <div class="payment-unavailable-alert" role="alert">
                    <strong>Pemberitahuan:</strong>
                    {{ $errorMessage ?? $errors->first('payment') ?? session('status') }}
                </div>
            @endif

            <div class="checkout-result-total">
                <span>Total pesanan</span><strong><x-price :amount="$order->total" /></strong>
            </div>

            <div class="payment-options-grid">
                {{-- Opsi 1: Coba buat tautan pembayaran lagi --}}
                <div class="payment-action-card">
                    <form method="post" action="{{ route('storefront.checkout.payment.retry') }}">
                        @csrf
                        <button class="button button-primary" type="submit">Coba Buat Tautan Pembayaran Lagi</button>
                    </form>
                    <p class="payment-action-hint">Gunakan tombol ini jika ingin mencoba kembali membuat invoice gateway Xendit.</p>
                </div>

                {{-- Opsi 2: Beralih ke COD --}}
                <div class="payment-action-card">
                    <form method="post" action="{{ route('storefront.checkout.payment.switch-cod') }}">
                        @csrf
                        <button class="button button-secondary" type="submit">Beralih ke Bayar di Tempat (COD)</button>
                    </form>
                    <p class="payment-action-hint">Pesanan akan langsung diproses tanpa bayar online. Bayar tunai saat kurir mengantarkan barang ke alamatmu.</p>
                </div>

                {{-- Opsi 3: Batalkan pesanan & kembalikan ke keranjang --}}
                <div class="payment-action-card">
                    <form method="post" action="{{ route('storefront.checkout.payment.cancel') }}" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini dan mengembalikan barang ke keranjang belanja?');">
                        @csrf
                        <button class="button button-danger-outline" type="submit">Batalkan Pesanan & Kembali ke Keranjang</button>
                    </form>
                    <p class="payment-action-hint">Batalkan pesanan ini, lepaskan cadangan stok, dan kembali ke keranjang untuk membuat pesanan baru.</p>
                </div>
            </div>

            @if (app()->environment('local'))
                <div class="payment-dev-box">
                    <p><strong>[Mode Pengujian Lokal]</strong> Jika Anda menguji di localhost tanpa API Key Xendit live:</p>
                    <form method="post" action="{{ route('storefront.checkout.payment.dev-simulate') }}">
                        @csrf
                        <button class="button button-dark" type="submit">Simulasikan Pembayaran Berhasil (Dev Mode)</button>
                    </form>
                </div>
            @endif

            <p class="checkout-result-note" style="margin-top: 16px;">
                Ingin mengecek status nanti? Anda juga bisa <a href="{{ route('storefront.orders.track') }}">melacak pesanan dengan ID {{ $order->id }}</a>.
            </p>
        </div>
    </section>
@endsection
