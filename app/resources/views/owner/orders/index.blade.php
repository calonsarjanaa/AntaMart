@extends('layouts.owner')

@section('title', 'Pesanan')

@section('breadcrumbs')
    <a href="{{ route('owner.dashboard') }}">Dashboard</a><span aria-hidden="true">/</span><span>Pesanan</span>
@endsection

@section('content')
    <section class="owner-orders-page" aria-labelledby="owner-orders-title">
        <div class="owner-page-heading">
            <div><p class="section-eyebrow">OPERASIONAL TOKO</p><h1 id="owner-orders-title">Pesanan</h1></div>
            <span class="owner-result-count">{{ $orders->total() }} pesanan</span>
        </div>

        <form class="owner-order-filters" method="get" action="{{ route('owner.orders.index') }}">
            <div class="form-field owner-search-field">
                <label class="field-label" for="order-search">Cari pesanan atau email</label>
                <input id="order-search" class="field-input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ID pesanan atau email">
            </div>
            <div class="form-field">
                <label class="field-label" for="order-status-filter">Status pesanan</label>
                <select id="order-status-filter" class="field-input" name="status">
                    <option value="">Semua status</option>
                    @foreach ($orderStatuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="field-label" for="payment-status-filter">Status pembayaran</label>
                <select id="payment-status-filter" class="field-input" name="payment_status">
                    <option value="">Semua status</option>
                    @foreach ($paymentStatuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['payment_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="owner-filter-actions">
                <button class="button button-primary" type="submit">Terapkan</button>
                <a class="button button-secondary" href="{{ route('owner.orders.index') }}">Reset</a>
            </div>
        </form>

        @if ($orders->isEmpty())
            <div class="owner-empty-panel">
                <h2>{{ $hasFilters ? 'Tidak ada pesanan yang cocok' : 'Belum ada pesanan' }}</h2>
                <p>{{ $hasFilters ? 'Ubah pencarian atau filter lalu coba lagi.' : 'Pesanan yang dibuat pelanggan akan muncul di sini.' }}</p>
            </div>
        @else
            <div class="owner-table-wrap">
                <table class="owner-table owner-orders-table">
                    <thead>
                        <tr><th>Pesanan</th><th>Pembeli</th><th>Tanggal</th><th>Status</th><th>Pembayaran</th><th>Item</th><th>Total</th><th><span class="visually-hidden">Aksi</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a class="owner-order-id" href="{{ route('owner.orders.show', $order->id) }}">{{ $order->id }}</a></td>
                                <td>
                                    @if ($order->customer)
                                        <span class="owner-buyer-name">{{ $order->customer->name }}</span>
                                        <small>Pelanggan · {{ $order->customer->email }}</small>
                                    @else
                                        <span class="owner-buyer-name">Tamu</span>
                                        <small>{{ $order->guest_email }}</small>
                                    @endif
                                </td>
                                <td>{{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td>
                                <td><x-status-badge :status="$order->status->value" /></td>
                                <td>@if ($order->payment)<x-status-badge :status="$order->payment->status->value" />@else<span class="owner-muted">—</span>@endif</td>
                                <td>{{ $order->items_count }}</td>
                                <td><x-price :amount="$order->total" /></td>
                                <td><a class="owner-table-action" href="{{ route('owner.orders.show', $order->id) }}">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <nav class="pagination" aria-label="Navigasi daftar pesanan">
                    @if ($orders->onFirstPage())<span class="pagination-disabled">Sebelumnya</span>@else<a href="{{ $orders->previousPageUrl() }}">Sebelumnya</a>@endif
                    <span>Halaman {{ $orders->currentPage() }} dari {{ $orders->lastPage() }}</span>
                    @if ($orders->hasMorePages())<a href="{{ $orders->nextPageUrl() }}">Berikutnya</a>@else<span class="pagination-disabled">Berikutnya</span>@endif
                </nav>
            @endif
        @endif
    </section>
@endsection
