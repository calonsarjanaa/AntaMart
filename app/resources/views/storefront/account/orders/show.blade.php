@extends('layouts.app')

@section('title', 'Detail pesanan')

@section('content')
    <section class="customer-order-page page-container" aria-labelledby="customer-order-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">AKUN PELANGGAN</p><h1 id="customer-order-title">Detail pesanan</h1></div>
            <a class="continue-shopping" href="{{ route('storefront.account.orders.index') }}">← Riwayat pesanan</a>
        </div>

        <div class="order-result-layout">
            <div class="order-result-main">
                <section class="checkout-form-section customer-order-overview" aria-labelledby="customer-order-overview-title">
                    <div class="customer-order-heading">
                        <div>
                            <span class="customer-order-label">Nomor pesanan</span>
                            <h2 id="customer-order-overview-title">{{ $order->id }}</h2>
                        </div>
                        <x-status-badge :status="$order->status->value" />
                    </div>
                    <p class="customer-order-created">Dibuat {{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</p>
                    <div class="tracking-statuses">
                        <span><span>Status pesanan</span><x-status-badge :status="$order->status->value" /></span>
                        <span><span>Status pembayaran</span><x-status-badge :status="$order->payment?->status?->value ?? 'pending'" /></span>
                    </div>
                    @if ($order->payment?->payment_method?->value === 'cod')
                        <p class="tracking-status-copy">Metode pembayaran: Bayar di tempat (COD).</p>
                    @endif
                    @if ($order->status->value === 'pending_payment')
                        <p class="tracking-status-copy">Pesanan menunggu konfirmasi pembayaran.</p>
                    @elseif ($order->status->value === 'processing')
                        @if ($order->payment?->payment_method?->value === 'cod' && $order->payment?->status?->value !== 'paid')
                            <p class="tracking-status-copy">Siapkan pembayaran tunai saat pesanan diterima. Toko akan mencatat pembayaran setelah uang diterima.</p>
                        @else
                            <p class="tracking-status-copy">Pembayaran dikonfirmasi dan pesanan sedang diproses.</p>
                        @endif
                    @elseif ($order->status->value === 'shipped' && $order->payment?->payment_method?->value === 'cod' && $order->payment?->status?->value !== 'paid')
                        <p class="tracking-status-copy">Pesanan telah dikirim. Siapkan pembayaran tunai saat pesanan diterima.</p>
                    @elseif ($order->status->value === 'expired' && $order->payment?->status?->value === 'paid')
                        <x-alert tone="warning" class="order-result-alert">Pembayaran diterima setelah pesanan kedaluwarsa. Pengembalian dana ditangani secara manual oleh toko.</x-alert>
                    @endif
                </section>

                <section class="checkout-form-section customer-order-items" aria-labelledby="customer-order-items-title">
                    <h2 id="customer-order-items-title">Produk dalam pesanan</h2>
                    @foreach ($order->items as $item)
                        <div class="order-result-item">
                            <div>
                                <strong>{{ $item->product_snapshot['name'] ?? 'Produk' }}</strong>
                                <span>{{ $item->variant_snapshot['name'] ?? $item->sku }} · {{ $item->quantity }} item</span>
                                <small><x-price :amount="$item->unit_price" /> / item</small>
                            </div>
                            <strong><x-price :amount="$item->subtotal" /></strong>
                        </div>
                    @endforeach
                    <div class="order-result-totals">
                        <div><span>Subtotal produk</span><strong><x-price :amount="$order->subtotal" /></strong></div>
                        <div><span>Ongkos kirim</span><strong><x-price :amount="$order->shipping_cost" /></strong></div>
                        <div><span>Total</span><strong><x-price :amount="$order->total" /></strong></div>
                    </div>
                </section>

                <section class="checkout-form-section customer-order-address" aria-labelledby="customer-order-address-title">
                    <h2 id="customer-order-address-title">Alamat pengiriman</h2>
                    @if ($order->address)
                        <strong>{{ $order->address->recipient_name }}</strong>
                        <p>{{ $order->address->phone }}<br>{{ $order->address->address_line1 }}@if ($order->address->address_line2), {{ $order->address->address_line2 }}@endif<br>{{ $order->address->city }}, {{ $order->address->province }} {{ $order->address->postal_code }}<br>{{ $order->address->country }}</p>
                    @else
                        <p>Informasi alamat tidak tersedia.</p>
                    @endif
                </section>

                <section class="checkout-form-section tracking-shipment-panel" aria-labelledby="customer-order-shipment-title">
                    <h2 id="customer-order-shipment-title">Pengiriman</h2>
                    @if ($order->shipment)
                        <p>{{ $order->shipment->method === 'manual' ? 'Pengiriman dikelola manual oleh toko.' : 'Informasi pengiriman tersedia.' }}</p>
                        @if ($order->shipment->tracking_number)<div><span>Nomor resi</span><strong>{{ $order->shipment->tracking_number }}</strong></div>@endif
                        @if ($order->shipment->shipped_at)<div><span>Dikirim</span><strong>{{ $order->shipment->shipped_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>@endif
                        @if ($order->shipment->delivered_at)<div><span>Diterima</span><strong>{{ $order->shipment->delivered_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>@endif
                        @if ($order->shipment->tracking_url)<a class="text-link" href="{{ $order->shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">Lacak pengiriman</a>@endif
                    @else
                        <p>Informasi pengiriman belum tersedia.</p>
                    @endif
                </section>

                @if ($order->refund)
                    <section class="checkout-form-section customer-order-refund" aria-labelledby="customer-order-refund-title">
                        <h2 id="customer-order-refund-title">Pengembalian dana</h2>
                        <div class="tracking-statuses"><span>Status pengembalian dana</span><x-status-badge :status="$order->refund->status->value" /></div>
                        <div class="tracking-totals">
                            <div><span>Jumlah</span><strong><x-price :amount="$order->refund->amount" /></strong></div>
                            @if ($order->refund->refunded_at)<div><span>Dicatat selesai</span><strong>{{ $order->refund->refunded_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>@endif
                        </div>
                        <p>Pengembalian dana diproses secara manual oleh toko.</p>
                    </section>
                @endif

                @if ($order->latestCancellationRequest || $canRequestCancellation)
                    <section class="checkout-form-section tracking-cancellation-panel" aria-labelledby="customer-order-cancellation-title">
                        <h2 id="customer-order-cancellation-title">Permintaan pembatalan</h2>
                        @if ($cancellationToDisplay)
                            <div class="cancellation-request-summary"><span>Status</span><x-status-badge :status="$cancellationToDisplay->status->value" /></div>
                            <p>{{ $cancellationToDisplay->reason }}</p>
                            @if ($cancellationToDisplay->rejection_reason)
                                <p>Catatan toko: {{ $cancellationToDisplay->rejection_reason }}</p>
                            @endif
                        @endif

                        @if ($canWithdrawCancellation)
                            <p>Permintaan menunggu tinjauan dan dapat ditarik sebelum toko membuat keputusan.</p>
                            <form class="cancellation-action-form" method="post" action="{{ route('storefront.account.orders.cancellation.withdraw', [$order->id, $pendingCancellation->id]) }}">
                                @csrf
                                <button class="button button-secondary" type="submit">Tarik permintaan</button>
                            </form>
                        @elseif ($canRequestCancellation)
                            <p>Permintaan akan ditinjau oleh toko. Jika pesanan yang sudah dibayar dibatalkan, pengembalian dana ditangani secara manual.</p>
                            <form class="cancellation-action-form" method="post" action="{{ route('storefront.account.orders.cancellation.store', $order->id) }}">
                                @csrf
                                <div class="form-field">
                                    <label class="field-label" for="customer-cancellation-reason">Alasan pembatalan <span class="required-mark" aria-hidden="true">*</span></label>
                                    <textarea id="customer-cancellation-reason" name="reason" class="field-input cancellation-reason-input @error('reason') field-input-invalid @enderror" maxlength="2000" required @error('reason') aria-invalid="true" aria-describedby="customer-cancellation-reason-error" @enderror>{{ old('reason') }}</textarea>
                                    @error('reason')<p class="field-error" id="customer-cancellation-reason-error">{{ $message }}</p>@enderror
                                </div>
                                <button class="button button-primary" type="submit">Kirim permintaan pembatalan</button>
                            </form>
                        @endif
                    </section>
                @endif
            </div>

            <aside class="checkout-form-section order-result-meta customer-order-summary">
                <h2>Ringkasan</h2>
                <div><span>Nomor pesanan</span><strong>{{ $order->id }}</strong></div>
                <div><span>Tanggal</span><strong>{{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>
                @if ($order->payment?->paid_at)<div><span>Dibayar</span><strong>{{ $order->payment->paid_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>@endif
                @if ($order->expires_at && $order->status->value === 'pending_payment')<div><span>Batas pembayaran</span><strong>{{ $order->expires_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>@endif
                @if ($order->shipment?->tracking_number)<div><span>Nomor resi</span><strong>{{ $order->shipment->tracking_number }}</strong></div>@endif
            </aside>
        </div>
    </section>
@endsection
