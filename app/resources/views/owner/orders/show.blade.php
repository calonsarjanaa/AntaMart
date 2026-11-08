@extends('layouts.owner')

@section('title', 'Detail pesanan')

@section('breadcrumbs')
    <a href="{{ route('owner.dashboard') }}">Dashboard</a><span aria-hidden="true">/</span>
    <a href="{{ route('owner.orders.index') }}">Pesanan</a><span aria-hidden="true">/</span><span>{{ $order->id }}</span>
@endsection

@section('content')
    <section class="owner-order-page" aria-labelledby="owner-order-title">
        <div class="owner-page-heading">
            <div><p class="section-eyebrow">DETAIL PESANAN</p><h1 id="owner-order-title">{{ $order->id }}</h1></div>
            <x-status-badge :status="$order->status->value" />
        </div>

        <div class="owner-order-grid">
            <div class="owner-order-main">
                <section class="owner-panel" aria-labelledby="owner-order-items-title">
                    <div class="owner-panel-heading"><h2 id="owner-order-items-title">Item pesanan</h2><span>{{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</span></div>
                    <div class="owner-table-wrap">
                        <table class="owner-table">
                            <thead><tr><th>Produk</th><th>SKU</th><th>Jumlah</th><th>Harga saat checkout</th><th>Subtotal</th></tr></thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td><span class="owner-buyer-name">{{ $item->product_snapshot['name'] ?? 'Produk' }}</span><small>{{ $item->variant_snapshot['name'] ?? 'Varian' }}</small></td>
                                        <td>{{ $item->sku }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td><x-price :amount="$item->unit_price" /></td>
                                        <td><x-price :amount="$item->subtotal" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="owner-order-totals">
                        <div><span>Subtotal produk</span><strong><x-price :amount="$order->subtotal" /></strong></div>
                        <div><span>Ongkos kirim</span><strong><x-price :amount="$order->shipping_cost" /></strong></div>
                        <div><span>Total pesanan</span><strong><x-price :amount="$order->total" /></strong></div>
                    </div>
                </section>

                <section class="owner-panel" aria-labelledby="owner-order-state-title">
                    <h2 id="owner-order-state-title">Status pembayaran dan pengiriman</h2>
                    <div class="owner-state-grid">
                        <div class="owner-state-card">
                            <span>Pembayaran</span>
                            @if ($order->payment)
                                <x-status-badge :status="$order->payment->status->value" />
                                @if ($order->payment->xendit_invoice_id)<small>Invoice {{ $order->payment->xendit_invoice_id }}</small>@endif
                                @if ($order->payment->paid_at)<small>Dibayar {{ $order->payment->paid_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                                @if ($order->payment->expired_at)<small>Kedaluwarsa {{ $order->payment->expired_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                                @if ($order->payment->late_payment_detected_at)<x-alert tone="warning">Pembayaran diterima setelah pesanan kedaluwarsa.</x-alert>@endif
                            @else
                                <span class="owner-muted">Belum ada catatan pembayaran</span>
                            @endif
                        </div>
                        <div class="owner-state-card">
                            <span>Pengiriman</span>
                            @if ($order->shipment)
                                <x-status-badge :status="$order->status->value" />
                                <small>Metode: {{ $order->shipment->method === 'manual' ? 'Manual' : $order->shipment->method }}</small>
                                @if ($order->shipment->tracking_number)<small>Resi: {{ $order->shipment->tracking_number }}</small>@endif
                                @if ($order->shipment->tracking_url)<a href="{{ $order->shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">Buka tautan pelacakan</a>@endif
                                @if ($order->shipment->shipped_at)<small>Dikirim {{ $order->shipment->shipped_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                                @if ($order->shipment->delivered_at)<small>Diterima {{ $order->shipment->delivered_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                            @else
                                <span class="owner-muted">Belum dikirim</span>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="owner-panel" aria-labelledby="owner-order-requests-title">
                    <h2 id="owner-order-requests-title">Pembatalan dan pengembalian dana</h2>
                    <div class="owner-state-grid">
                        <div class="owner-state-card">
                            <span>Permintaan pembatalan</span>
                            @if ($order->latestCancellationRequest)
                                <x-status-badge :status="$order->latestCancellationRequest->status->value" />
                                <small>Alasan: {{ $order->latestCancellationRequest->reason }}</small>
                                @if ($order->latestCancellationRequest->rejection_reason)<small>Catatan keputusan: {{ $order->latestCancellationRequest->rejection_reason }}</small>@endif
                                <small>Diajukan {{ $order->latestCancellationRequest->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>
                            @else
                                <span class="owner-muted">Tidak ada permintaan pembatalan</span>
                            @endif
                        </div>
                        <div class="owner-state-card">
                            <span>Pengembalian dana</span>
                            @if ($order->refund)
                                <x-status-badge :status="$order->refund->status->value" />
                                <small>Jumlah: <x-price :amount="$order->refund->amount" /></small>
                                @if ($order->refund->reason)<small>Alasan: {{ $order->refund->reason }}</small>@endif
                                @if ($order->refund->refunded_at)<small>Dicatat {{ $order->refund->refunded_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                                <p>Pengembalian dana dicatat manual; transfer dana tidak dilakukan oleh sistem.</p>
                            @else
                                <span class="owner-muted">Tidak ada catatan pengembalian dana</span>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="owner-panel" aria-labelledby="owner-order-activity-title">
                    <h2 id="owner-order-activity-title">Aktivitas terbaru</h2>
                    @if ($auditLogs->isEmpty())
                        <p class="owner-muted">Belum ada aktivitas tercatat.</p>
                    @else
                        <ol class="owner-activity-list">
                            @foreach ($auditLogs as $log)
                                <li>
                                    <div><strong>{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $log->event)) }}</strong><small>{{ $log->actor?->name ?? \Illuminate\Support\Str::headline($log->actor_type ?? 'system') }}</small></div>
                                    <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</time>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    @if ($auditLogs->count() === 20)<small class="owner-activity-note">Menampilkan 20 aktivitas terbaru.</small>@endif
                </section>
            </div>

            <aside class="owner-panel owner-order-buyer" aria-labelledby="owner-order-buyer-title">
                <h2 id="owner-order-buyer-title">Pembeli</h2>
                @if ($order->customer)
                    <span class="owner-buyer-type">Pelanggan</span>
                    <strong>{{ $order->customer->name }}</strong>
                    <a href="mailto:{{ $order->customer->email }}">{{ $order->customer->email }}</a>
                @else
                    <span class="owner-buyer-type">Tamu</span>
                    <a href="mailto:{{ $order->guest_email }}">{{ $order->guest_email }}</a>
                    @if ($order->guest_phone)<a href="tel:{{ $order->guest_phone }}">{{ $order->guest_phone }}</a>@endif
                @endif

                <hr>
                <h3>Alamat pengiriman</h3>
                @if ($order->address)
                    <strong>{{ $order->address->recipient_name }}</strong>
                    <p>{{ $order->address->phone }}<br>{{ $order->address->address_line1 }}@if ($order->address->address_line2), {{ $order->address->address_line2 }}@endif<br>{{ $order->address->city }}, {{ $order->address->province }} {{ $order->address->postal_code }}<br>{{ $order->address->country }}</p>
                @else
                    <p class="owner-muted">Alamat tidak tersedia.</p>
                @endif
            </aside>
        </div>
    </section>
@endsection
