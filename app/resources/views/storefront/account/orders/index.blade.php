@extends('layouts.app')

@section('title', 'Riwayat pesanan')

@section('content')
    <section class="customer-orders-page page-container" aria-labelledby="customer-orders-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">AKUN PELANGGAN</p><h1 id="customer-orders-title">Riwayat pesanan</h1></div>
            <a class="continue-shopping" href="{{ route('storefront.account.index') }}">← Kembali ke akun</a>
        </div>

        @if ($orders->isEmpty())
            <x-empty-state class="customer-orders-empty" title="Belum ada pesanan" description="Pesanan yang kamu buat saat masuk ke akun akan muncul di sini.">
                <x-slot:footer><a class="button button-primary" href="{{ route('storefront.home') }}#products">Lihat produk</a></x-slot:footer>
            </x-empty-state>
        @else
            <div class="customer-order-list">
                @foreach ($orders as $order)
                    <article class="customer-order-card">
                        <div class="customer-order-heading">
                            <div>
                                <span class="customer-order-label">Nomor pesanan</span>
                                <strong>{{ $order->id }}</strong>
                            </div>
                            <x-status-badge :status="$order->status->value" />
                        </div>
                        <div class="customer-order-meta">
                            <span>{{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</span>
                            <span>{{ $order->items_count }} item</span>
                            @if ($order->payment)<span>Pembayaran <x-status-badge :status="$order->payment->status->value" />{{ $order->payment->payment_method->value === 'cod' ? ' · COD' : '' }}</span>@endif
                        </div>
                        <div class="customer-order-footer">
                            <strong><x-price :amount="$order->total" /></strong>
                            <a class="button button-secondary" href="{{ route('storefront.account.orders.show', $order->id) }}">Lihat detail</a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($orders->hasPages())
                <nav class="pagination" aria-label="Navigasi riwayat pesanan">
                    @if ($orders->onFirstPage())
                        <span class="pagination-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $orders->previousPageUrl() }}">Sebelumnya</a>
                    @endif
                    <span>Halaman {{ $orders->currentPage() }} dari {{ $orders->lastPage() }}</span>
                    @if ($orders->hasMorePages())
                        <a href="{{ $orders->nextPageUrl() }}">Berikutnya</a>
                    @else
                        <span class="pagination-disabled">Berikutnya</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
