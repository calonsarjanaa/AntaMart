@extends('layouts.app')

@section('title', 'Lacak pesanan')

@section('content')
    <section class="tracking-page page-container" aria-labelledby="tracking-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">ANTAMART</p><h1 id="tracking-title">Lacak pesanan</h1></div>
        </div>

        <div class="tracking-layout">
            <section class="checkout-form-section tracking-form-panel" aria-labelledby="tracking-form-title">
                <h2 id="tracking-form-title">Masukkan informasi pesanan</h2>
                <p>Gunakan ID pesanan dan email yang dipakai saat checkout.</p>
                <form method="post" action="{{ route('storefront.orders.track.lookup') }}">
                    @csrf
                    <x-form-field name="order_id" label="ID pesanan" :value="old('order_id')" required autocomplete="off" />
                    <x-form-field name="email" label="Email checkout" type="email" :value="old('email')" required autocomplete="email" />
                    @error('lookup')<p class="field-error tracking-lookup-error">{{ $message }}</p>@enderror
                    <button class="button button-primary tracking-submit" type="submit">Cari pesanan</button>
                </form>
                <small>Pencarian dibatasi untuk melindungi informasi pesanan.</small>
            </section>

            @if ($order)
                @php
                    $orderStatus = $order->status->value;
                    $paymentStatus = $order->payment?->status?->value;
                    $paymentMethod = $order->payment?->payment_method?->value;
                    $latePayment = $orderStatus === 'expired' && $paymentStatus === 'paid';
                @endphp
                <div class="tracking-result">
                    <section class="checkout-form-section tracking-order-panel" aria-labelledby="tracking-order-title">
                        <div class="tracking-result-heading">
                            <div>
                                <p class="section-eyebrow">PESANAN {{ $order->id }}</p>
                                <h2 id="tracking-order-title">Status pesanan</h2>
                            </div>
                            <a href="{{ route('storefront.orders.track', ['new' => 1]) }}">Cari pesanan lain</a>
                        </div>
                        <div class="tracking-statuses">
                            <span><span>Status pesanan</span><x-status-badge :status="$orderStatus" /></span>
                            <span><span>Status pembayaran</span><x-status-badge :status="$paymentStatus ?? 'pending'" /></span>
                        </div>
                        @if ($orderStatus === 'pending_payment')
                            <p class="tracking-status-copy">Pesanan menunggu konfirmasi pembayaran. Status akan berubah setelah sistem menerima pembaruan dari penyedia pembayaran.</p>
                        @elseif ($orderStatus === 'processing')
                            @if ($paymentMethod === 'cod' && $paymentStatus !== 'paid')
                                <p class="tracking-status-copy">Pesanan sedang diproses. Siapkan pembayaran tunai sebesar <x-price :amount="$order->total" /> saat pesanan diterima.</p>
                            @else
                                <p class="tracking-status-copy">Pembayaran telah dikonfirmasi dan pesanan sedang diproses oleh toko.</p>
                            @endif
                        @elseif ($orderStatus === 'expired')
                            <p class="tracking-status-copy">Batas waktu pembayaran telah lewat dan reservasi stok dilepas.</p>
                        @elseif ($orderStatus === 'cancelled')
                            <p class="tracking-status-copy">Pesanan telah dibatalkan. Pengembalian dana, jika diperlukan, ditangani secara manual oleh toko.</p>
                        @elseif ($orderStatus === 'shipped')
                            @if ($paymentMethod === 'cod' && $paymentStatus !== 'paid')
                                <p class="tracking-status-copy">Pesanan telah dikirim. Siapkan pembayaran tunai saat pesanan diterima.</p>
                            @else
                                <p class="tracking-status-copy">Pesanan telah dikirim. Lihat informasi resi di bagian pengiriman.</p>
                            @endif
                        @elseif ($orderStatus === 'completed')
                            <p class="tracking-status-copy">Pesanan telah ditandai selesai.</p>
                        @endif
                        @if ($paymentMethod)
                            <p class="tracking-status-copy">Metode pembayaran: {{ $paymentMethod === 'cod' ? 'Bayar di tempat (COD)' : 'Xendit' }}.</p>
                        @endif
                        @if ($latePayment)
                            <x-alert tone="warning" class="tracking-alert">Pembayaran diterima setelah pesanan kedaluwarsa. Pengembalian dana perlu ditangani secara manual oleh toko.</x-alert>
                        @elseif ($order->refund?->status?->value === 'manual_refund_pending')
                            <x-alert tone="warning" class="tracking-alert">Pengembalian dana sedang ditangani secara manual oleh toko.</x-alert>
                        @elseif ($order->refund?->status?->value === 'refunded')
                            <x-alert tone="success" class="tracking-alert">Pengembalian dana manual telah dicatat selesai.</x-alert>
                        @endif
                        <div class="tracking-totals">
                            <div><span>Subtotal produk</span><strong><x-price :amount="$order->subtotal" /></strong></div>
                            <div><span>Ongkos kirim</span><strong><x-price :amount="$order->shipping_cost" /></strong></div>
                            <div><span>Total</span><strong><x-price :amount="$order->total" /></strong></div>
                        </div>
                    </section>

                    <section class="checkout-form-section tracking-items-panel" aria-labelledby="tracking-items-title">
                        <h2 id="tracking-items-title">Produk dalam pesanan</h2>
                        @foreach ($order->items as $item)
                            <div class="order-result-item">
                                <div>
                                    <strong>{{ $item->product_snapshot['name'] ?? 'Produk' }}</strong>
                                    <span>{{ $item->variant_snapshot['name'] ?? $item->sku }} · {{ $item->quantity }} item</span>
                                </div>
                                <strong><x-price :amount="$item->subtotal" /></strong>
                            </div>
                        @endforeach
                    </section>

                    <section class="checkout-form-section tracking-shipment-panel" aria-labelledby="tracking-shipment-title">
                        <h2 id="tracking-shipment-title">Pengiriman</h2>
                        @if ($order->shipment)
                            <p>{{ $order->shipment->method === 'manual' ? 'Pengiriman dikelola manual oleh toko.' : 'Informasi pengiriman tersedia.' }}</p>
                            @if ($order->shipment->tracking_number)
                                <div><span>Nomor resi</span><strong>{{ $order->shipment->tracking_number }}</strong></div>
                            @endif
                            @if ($order->shipment->shipped_at)
                                <div><span>Dikirim</span><strong>{{ $order->shipment->shipped_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>
                            @endif
                            @if ($order->shipment->delivered_at)
                                <div><span>Diterima</span><strong>{{ $order->shipment->delivered_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>
                            @endif
                            @if ($order->shipment->tracking_url)
                                <a class="text-link" href="{{ $order->shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">Buka tautan pelacakan</a>
                            @endif
                        @else
                            <p>Informasi pengiriman belum tersedia.</p>
                        @endif
                    </section>

                    @if ($pendingCancellation || $latestCancellation || $orderStatus === 'processing')
                        <section class="checkout-form-section tracking-cancellation-panel" aria-labelledby="tracking-cancellation-title">
                            <h2 id="tracking-cancellation-title">Permintaan pembatalan</h2>
                            @if ($pendingCancellation)
                                <div class="cancellation-request-summary">
                                    <span>Status permintaan</span>
                                    <x-status-badge :status="$pendingCancellation->status->value" />
                                </div>
                                <p>Permintaan ini menunggu tinjauan toko. Kamu dapat menariknya sebelum toko membuat keputusan.</p>
                                @if ($cancellationWithdrawUrl)
                                    <form class="cancellation-action-form" method="post" action="{{ $cancellationWithdrawUrl }}">
                                        @csrf
                                        <x-form-field name="guest_email" label="Email yang digunakan saat checkout" type="email" required autocomplete="email" />
                                        <button class="button button-secondary" type="submit">Tarik permintaan</button>
                                    </form>
                                @endif
                            @elseif ($orderStatus === 'processing')
                                @if ($latestCancellation)
                                    <div class="cancellation-request-summary">
                                        <span>Permintaan sebelumnya</span>
                                        <x-status-badge :status="$latestCancellation->status->value" />
                                    </div>
                                    @if ($latestCancellation->status->value === 'rejected' && $latestCancellation->rejection_reason)
                                        <p>Catatan toko: {{ $latestCancellation->rejection_reason }}</p>
                                    @endif
                                @endif
                                <p>Permintaan akan ditinjau oleh toko. Pesanan yang sudah dibayar dan dibatalkan memerlukan pengembalian dana manual.</p>
                                @if ($cancellationStoreUrl)
                                    <form class="cancellation-action-form" method="post" action="{{ $cancellationStoreUrl }}">
                                        @csrf
                                        <x-form-field name="guest_email" label="Email yang digunakan saat checkout" type="email" required autocomplete="email" />
                                        <div class="form-field">
                                            <label class="field-label" for="cancellation-reason">Alasan pembatalan <span class="required-mark" aria-hidden="true">*</span></label>
                                            <textarea id="cancellation-reason" name="reason" class="field-input cancellation-reason-input @error('reason') field-input-invalid @enderror" maxlength="2000" required @error('reason') aria-invalid="true" aria-describedby="cancellation-reason-error" @enderror>{{ old('reason') }}</textarea>
                                            @error('reason')<p class="field-error" id="cancellation-reason-error">{{ $message }}</p>@enderror
                                        </div>
                                        <button class="button button-primary" type="submit">Kirim permintaan pembatalan</button>
                                    </form>
                                @endif
                            @else
                                <div class="cancellation-request-summary">
                                    <span>Permintaan terakhir</span>
                                    <x-status-badge :status="$latestCancellation->status->value" />
                                </div>
                                <p>{{ $latestCancellation->reason }}</p>
                            @endif
                        </section>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
